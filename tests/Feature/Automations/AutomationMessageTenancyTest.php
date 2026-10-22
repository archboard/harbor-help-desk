<?php

use App\Enums\AutomationRunStatus;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\DatabaseNotification;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->base = 'http://acme.harbor.test/api/v1/automations';

    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->other = $other;
    [$this->macroB, $this->ticketB] = $other->execute(fn () => [Macro::factory()->create(['body' => 'B macro']), Ticket::factory()->create()]);

    // A member of both workspaces.
    $this->shared = User::factory()->create();
    $this->workspace->users()->attach($this->shared, ['role' => WorkspaceRole::ADMIN]);
    $other->users()->attach($this->shared, ['role' => WorkspaceRole::ADMIN]);
    $this->desk->users()->attach($this->shared, ['role' => DeskRole::LEAD, 'workspace_id' => $this->workspace->id]);
    $other->execute(fn () => $this->ticketB->desk->users()->attach($this->shared, ['role' => DeskRole::LEAD, 'workspace_id' => $other->id]));
});

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function oneStep(string $action, array $config): array
{
    return ['name' => 'Auto', 'definition' => [
        'trigger' => ['type' => 'ticket.created', 'config' => []],
        'steps' => [['id' => 's1', 'type' => 'action', 'action' => $action, 'config' => $config]],
    ]];
}

test('apply_macro and notify can not be saved with another workspace\'s macro or user', function () {
    actAs($this->admin, $this->workspace);
    $outsider = User::factory()->create();
    $this->other->users()->attach($outsider, ['role' => WorkspaceRole::AGENT]);

    $this->postJson($this->base, oneStep('apply_macro', ['macro_id' => $this->macroB->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.macro_id']);
    $this->postJson($this->base, oneStep('notify', ['recipients' => 'users', 'user_ids' => [$outsider->id], 'subject' => 'Hi', 'body' => 'Hi']))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.user_ids.0']);

    $own = Macro::factory()->create();
    $this->postJson($this->base, oneStep('apply_macro', ['macro_id' => $own->id]))->assertCreated();
    $this->postJson($this->base, oneStep('notify', ['recipients' => 'users', 'user_ids' => [$this->shared->id], 'subject' => 'Hi', 'body' => 'Hi']))->assertCreated();
});

test('stored configs pointing at another workspace fail the run and leave its data alone', function () {
    $outsider = User::factory()->create();
    $this->other->users()->attach($outsider, ['role' => WorkspaceRole::AGENT]);
    $ticket = Ticket::factory()->create();

    foreach ([
        ['apply_macro', ['macro_id' => $this->macroB->id]],
        ['notify', ['recipients' => 'users', 'user_ids' => [$outsider->id], 'subject' => 'Hi', 'body' => 'Hi']],
    ] as [$action, $config]) {
        Automation::query()->delete();
        Automation::factory()->enabled()->triggeredBy('ticket.created', $action, $config)->create();
        $ticket->recordEvent(TicketEventType::CREATED, null);

        expect(AutomationRun::query()->latest('id')->firstOrFail()->status)->toBe(AutomationRunStatus::FAILED, $action);
    }

    $this->other->execute(function () use ($outsider) {
        expect($this->ticketB->refresh()->comments()->count())->toBe(0)
            ->and($this->ticketB->events()->count())->toBe(0)
            ->and(DatabaseNotification::query()->count())->toBe(0)
            ->and($outsider->notifications()->count())->toBe(0);
    });
});

test('a member of two workspaces is notified only in the workspace whose automation fired', function () {
    $ticket = Ticket::factory()->for($this->desk)->create();
    $creator = $this->shared;

    Automation::factory()->enabled()
        ->triggeredBy('ticket.created', 'notify', ['recipients' => 'desk_leads', 'subject' => 'Heads up', 'body' => 'Look'])
        ->create(['created_by' => $creator->id]);
    Automation::factory()->enabled()->triggeredBy('ticket.created', 'move_desk', ['desk_id' => 'dsk_missing'])->create(['created_by' => $creator->id, 'position' => 2]);
    $ticket->recordEvent(TicketEventType::CREATED, null);

    $rows = DatabaseNotification::query()->withoutGlobalScopes()->where('notifiable_id', $this->shared->id)->where('type', 'automation')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('workspace_id')->unique()->all())->toBe([$this->workspace->id])
        ->and($rows->pluck('data.title')->all())->toContain('Heads up');

    $this->other->execute(fn () => expect(DatabaseNotification::query()->count())->toBe(0));
});
