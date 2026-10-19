<?php

use App\Enums\DeskRole;
use App\Enums\TicketPriority;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
});

test('agents open tickets on their desk', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->postJson("http://acme.harbor.test/api/v1/desks/{$desk->id}/tickets", [
        'subject' => 'Printer jam',
        'requester' => ['name' => 'Pat Lee', 'email' => 'pat@example.com'],
        'assignee_ids' => [$agent->id],
    ])
        ->assertCreated()
        ->assertJsonPath('number', 1)
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('requester.email', 'pat@example.com')
        ->assertJsonPath('assignees.0.id', $agent->id);

    expect(Ticket::sole()->subject)->toBe('Printer jam');
});

test('opening a ticket validates its input', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->postJson("http://acme.harbor.test/api/v1/desks/{$desk->id}/tickets", ['priority' => 'whenever'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'priority', 'requester_id']);
});

test('another workspace tickets and desks are invisible', function () {
    ['workspace' => $workspace, 'agent' => $agent] = acmeWithAgent();
    [$foreignTicket, $foreignDesk] = Workspace::factory()->create()->execute(function () {
        $ticket = Ticket::factory()->create();

        return [$ticket, $ticket->desk];
    });
    $workspace->makeCurrent();
    actAs($agent, $workspace);

    $this->getJson('http://acme.harbor.test/api/v1/tickets')->assertJsonMissing(['id' => $foreignTicket->id]);
    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$foreignTicket->id}")->assertNotFound();
    $this->patchJson("http://acme.harbor.test/api/v1/tickets/{$foreignTicket->id}", ['subject' => 'Hijacked'])->assertNotFound();
    $this->putJson("http://acme.harbor.test/api/v1/tickets/{$foreignTicket->id}/tags", ['names' => ['x']])->assertNotFound();
    $this->postJson("http://acme.harbor.test/api/v1/desks/{$foreignDesk->id}/tickets", ['subject' => 'x'])->assertNotFound();

    expect(Ticket::withoutGlobalScope('workspace')->find($foreignTicket->id)->subject)->not->toBe('Hijacked');
});

test('the list shows visible tickets and filters them', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $urgent = Ticket::factory()->for($desk)->create(['priority' => TicketPriority::URGENT]);
    $urgent->tags()->attach(Tag::factory()->create(['name' => 'Billing']), ['workspace_id' => $workspace->id]);
    $assigned = Ticket::factory()->for($desk)->create();
    $assigned->assignees()->attach($agent, ['workspace_id' => $workspace->id]);
    Ticket::factory()->create();
    actAs($agent, $workspace);

    $this->getJson('http://acme.harbor.test/api/v1/tickets')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('http://acme.harbor.test/api/v1/tickets?priority=urgent')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $urgent->id);
    $this->getJson('http://acme.harbor.test/api/v1/tickets?tag=billing')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $urgent->id);
    $this->getJson("http://acme.harbor.test/api/v1/tickets?assignee_id={$agent->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
    $this->getJson('http://acme.harbor.test/api/v1/tickets?priority[]=urgent&priority[]=low')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $urgent->id);
    $this->getJson('http://acme.harbor.test/api/v1/tickets?assignee_id=me')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
    $this->getJson('http://acme.harbor.test/api/v1/tickets?status=nope')->assertJsonValidationErrors('status');
});

test('tickets are updated, moved, assigned and tagged, and the history reads back', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    $south = Desk::factory()->create(['name' => 'South']);
    $south->users()->attach($agent, ['role' => DeskRole::AGENT, 'workspace_id' => $workspace->id]);
    actAs($agent, $workspace);
    $url = "http://acme.harbor.test/api/v1/tickets/{$ticket->id}";

    $this->patchJson($url, ['priority' => 'high'])->assertOk()->assertJsonPath('priority', 'high');
    $this->putJson("{$url}/assignees", ['user_ids' => [$agent->id]])->assertOk()->assertJsonPath('0.id', $agent->id);
    $this->putJson("{$url}/tags", ['names' => ['Billing']])->assertOk()->assertJsonPath('0.name', 'Billing');
    $this->putJson("{$url}/desk", ['desk_id' => $south->id])->assertOk()->assertJsonPath('desk_id', $south->id);

    $this->getJson("{$url}/events")
        ->assertOk()
        ->assertJsonPath('data.*.description', [
            'Jane Doe changed the priority to High.',
            'Jane Doe assigned Jane Doe.',
            'Jane Doe added the tag “Billing”.',
            'Jane Doe moved the ticket to South.',
        ]);
});

test('moving to an unknown desk is a 404', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    actAs($agent, $workspace);

    $this->putJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/desk", ['desk_id' => 'dsk_missing'])->assertNotFound();
});

test('leads delete and restore tickets and agents cannot', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    $url = "http://acme.harbor.test/api/v1/tickets/{$ticket->id}";

    actAs($agent, $workspace);
    $this->deleteJson($url)->assertForbidden();

    actAs(deskMember($workspace, $desk, DeskRole::LEAD), $workspace);
    $this->deleteJson($url)->assertNoContent();
    $this->getJson($url)->assertNotFound();
    $this->postJson("{$url}/restore")->assertOk()->assertJsonPath('deleted_at', null);
});

test('workspace members off the desk cannot see its tickets', function () {
    ['workspace' => $workspace, 'desk' => $desk] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    $bystander = User::factory()->create();
    $workspace->users()->attach($bystander, ['role' => WorkspaceRole::AGENT]);
    actAs($bystander, $workspace);

    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}")->assertForbidden();
    $this->getJson('http://acme.harbor.test/api/v1/tickets')->assertOk()->assertJsonCount(0, 'data');
});
