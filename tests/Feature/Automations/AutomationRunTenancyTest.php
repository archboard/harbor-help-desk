<?php

use App\Enums\AutomationRunStatus;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->ticketA = Ticket::factory()->create();

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->other->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->bUser = User::factory()->create(['name' => 'Bea Secret']);
    $this->other->users()->attach($this->bUser, ['role' => WorkspaceRole::AGENT]);

    [$this->ticketB, $this->eventB, $this->deskB, $this->macroB, $this->automationB, $this->runB] = $this->other->execute(function () {
        $ticket = Ticket::factory()->create(['subject' => 'B private subject']);
        $automation = Automation::factory()->create(['name' => 'B secret automation']);
        $run = AutomationRun::query()->create([
            'workspace_id' => $this->other->id, 'automation_id' => $automation->id, 'ticket_id' => $ticket->id, 'depth' => 0,
            'status' => AutomationRunStatus::COMPLETED, 'definition' => $automation->definition, 'log' => [], 'state' => [],
        ]);

        return [$ticket, $ticket->recordEvent(TicketEventType::CREATED, null), Desk::factory()->create(['name' => 'B Desk']), Macro::factory()->create(['body' => 'B macro body']), $automation, $run];
    });

    $this->web = 'http://acme.harbor.test';
    $this->api = 'http://acme.harbor.test/api/v1';
});

test('a test run can not reach another workspace\'s ticket, event, desk, users or macros', function () {
    actAs($this->admin, $this->workspace);
    $note = ['id' => 'n', 'type' => 'action', 'action' => 'add_note', 'config' => ['body' => 'x']];
    $definition = fn (array $steps) => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => $steps];
    $assertLeaksNothing = function ($response) {
        $response->assertUnprocessable();
        expect($response->getContent())->not->toContain('B private subject', 'Bea Secret', 'B macro body', 'B Desk');
    };

    $assertLeaksNothing($this->postJson("{$this->api}/automations/test", ['definition' => $definition([$note]), 'ticket_id' => $this->ticketB->id]));
    $assertLeaksNothing($this->postJson("{$this->api}/automations/test", ['definition' => $definition([$note]), 'ticket_id' => $this->ticketA->id, 'ticket_event_id' => $this->eventB->id]));
    $assertLeaksNothing($this->postJson("{$this->api}/automations/test", ['definition' => $definition([$note]), 'ticket_id' => $this->ticketA->id, 'desk_id' => $this->deskB->id]));
    $assertLeaksNothing($this->postJson("{$this->api}/automations/test", ['definition' => $definition([
        ['id' => 'm', 'type' => 'action', 'action' => 'apply_macro', 'config' => ['macro_id' => $this->macroB->id]],
        ['id' => 'a', 'type' => 'action', 'action' => 'assign', 'config' => ['mode' => 'add', 'user_ids' => [$this->bUser->id]]],
        ['id' => 'd', 'type' => 'action', 'action' => 'move_desk', 'config' => ['desk_id' => $this->deskB->id]],
    ]), 'ticket_id' => $this->ticketA->id]));

    $this->actingAs($this->admin);
    $assertLeaksNothing($this->postJson("{$this->web}/automations/test", ['definition' => $definition([$note]), 'ticket_id' => $this->ticketB->id]));
});

test('another workspace\'s runs can not be listed or fetched', function () {
    actAs($this->admin, $this->workspace);
    $this->getJson("{$this->api}/automations/{$this->automationB->id}/runs")->assertNotFound();
    $this->getJson("{$this->api}/automation-runs/{$this->runB->id}")->assertNotFound();

    $this->actingAs($this->admin);
    $this->getJson("{$this->web}/automations/{$this->automationB->id}/runs")->assertNotFound();
    $this->getJson("{$this->web}/automation-runs/{$this->runB->id}")->assertNotFound();

    $ownRun = AutomationRun::query()->create([
        'workspace_id' => $this->workspace->id, 'automation_id' => ($own = Automation::factory()->create())->id, 'depth' => 0,
        'status' => AutomationRunStatus::COMPLETED, 'definition' => $own->definition, 'log' => [], 'state' => [],
    ]);
    $this->getJson("{$this->web}/automations/{$own->id}/runs")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $ownRun->id);
});
