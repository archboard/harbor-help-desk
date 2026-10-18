<?php

use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
});

test('agents set, list and cancel reminders and only see others ticket-level ones', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    $colleague = deskMember($workspace, $desk);
    TicketReminder::factory()->for($ticket)->create(['created_by' => $colleague->id]);
    $shared = TicketReminder::factory()->for($ticket)->ticketLevel()->create(['created_by' => $colleague->id]);
    actAs($agent, $workspace);
    $url = "http://acme.harbor.test/api/v1/tickets/{$ticket->id}/reminders";

    $mine = $this->postJson($url, ['remind_at' => now()->addDay()->toIso8601String(), 'note' => 'Follow up'])
        ->assertCreated()
        ->assertJsonPath('for_assignees', false)
        ->assertJsonPath('user_id', $agent->id)
        ->json('id');

    $this->getJson($url)->assertOk()->assertJsonPath('*.id', [$shared->id, $mine]);

    $this->deleteJson("http://acme.harbor.test/api/v1/reminders/{$mine}")->assertNoContent();
    $this->deleteJson("http://acme.harbor.test/api/v1/reminders/{$shared->id}")->assertForbidden();
});

test('reminders must be in the future', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->postJson('http://acme.harbor.test/api/v1/tickets/'.Ticket::factory()->for($desk)->create()->id.'/reminders', ['remind_at' => now()->subDay()->toIso8601String()])
        ->assertJsonValidationErrors('remind_at');
});

test('another workspace reminders cannot be cancelled', function () {
    ['workspace' => $workspace, 'agent' => $agent] = acmeWithAgent();
    $foreign = Workspace::factory()->create()->execute(fn () => TicketReminder::factory()->create());
    $workspace->makeCurrent();
    actAs($agent, $workspace);

    $this->deleteJson("http://acme.harbor.test/api/v1/reminders/{$foreign->id}")->assertNotFound();
});
