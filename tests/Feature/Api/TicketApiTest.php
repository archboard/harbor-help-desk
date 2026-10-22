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

    $response = $this->postJson("http://acme.harbor.test/api/v1/desks/{$desk->id}/tickets", [
        'subject' => 'Printer jam',
        'requester' => ['name' => 'Pat Lee', 'email' => 'pat@example.com'],
        'assignee_ids' => [$agent->id],
        'body' => 'It jams on every double-sided print.',
    ])->assertCreated();

    $ticket = Ticket::sole();
    $response->assertSee('"custom_fields":{}', false)->assertExactJson([
        'id' => $ticket->id,
        'number' => 1,
        'subject' => 'Printer jam',
        'status' => 'open',
        'status_label' => 'Open',
        'priority' => 'normal',
        'priority_label' => 'Normal',
        'source' => 'api',
        'desk_id' => $desk->id,
        'sla_policy_id' => null,
        'desk' => ['id' => $desk->id, 'name' => $desk->name, 'slug' => $desk->slug, 'assignment_strategy' => 'manual', 'sla_policy_id' => null, 'archived_at' => null],
        'due_at' => null,
        'custom_fields' => [],
        'requester' => ['id' => $ticket->requester_id, 'name' => 'Pat Lee', 'email' => 'pat@example.com'],
        'assignees' => [['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email]],
        'tags' => [],
        'sla_targets' => [],
        'created_at' => $ticket->created_at->toIso8601String(),
        'updated_at' => $ticket->updated_at->toIso8601String(),
        'deleted_at' => null,
    ]);

    expect(Ticket::sole())->subject->toBe('Printer jam')
        ->and(Ticket::sole()->comments()->sole())->body->toBe('It jams on every double-sided print.')->author_id->toBe($agent->id);
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
    $this->putJson("{$url}/assignees", ['user_ids' => [$agent->id]])
        ->assertOk()
        ->assertExactJson([['id' => $agent->id, 'name' => $agent->name, 'email' => $agent->email]]);
    $this->putJson("{$url}/tags", ['names' => ['Billing']])
        ->assertOk()
        ->assertExactJson([['id' => Tag::sole()->id, 'name' => 'Billing']]);
    $this->putJson("{$url}/desk", ['desk_id' => $south->id])->assertOk()->assertJsonPath('desk_id', $south->id);

    $created = $ticket->events()->oldest('id')->first();
    $this->getJson("{$url}/events")
        ->assertOk()
        ->assertJsonPath('data.0', [
            'id' => $created->id,
            'type' => $created->type->value,
            'description' => $created->description(),
            'actor_name' => $created->actorName(),
            'actor_type' => $created->actor_type,
            'actor_id' => $created->actor_id,
            'automation_run_id' => null,
            'properties' => $created->properties,
            'created_at' => $created->created_at->toIso8601String(),
        ])
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

test('agents claim the next waiting ticket, or get 204', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->postJson('http://acme.harbor.test/api/v1/tickets/claim')->assertNoContent();

    $ticket = Ticket::factory()->for($desk)->create();
    $this->postJson('http://acme.harbor.test/api/v1/tickets/claim')
        ->assertOk()
        ->assertJsonPath('id', $ticket->id)
        ->assertJsonPath('assignees.0.id', $agent->id);
});
