<?php

use App\Actions\Automations\RunAutomationsForEvent;
use App\Actions\Tickets\UpdateTicket;
use App\Automations\AutomationRegistry;
use App\Automations\Triggers\FieldChanged;
use App\Automations\Triggers\TicketCreated;
use App\Enums\AutomationRunStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Ticket;
use App\Models\User;
use Tests\Concerns\InWorkspace;
use Tests\Fakes\Automations\SetPriorityAction;

uses(InWorkspace::class);

beforeEach(function () {
    app()->instance(AutomationRegistry::class, new AutomationRegistry([TicketCreated::class, FieldChanged::class], [SetPriorityAction::class]));

    $this->ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
});

function priorityRule(string $from, string $to, array $attributes = []): Automation
{
    return Automation::factory()->enabled()->create([
        'trigger_type' => 'ticket.field_changed',
        'definition' => [
            'trigger' => ['type' => 'ticket.field_changed', 'config' => ['fields' => ['ticket.priority']]],
            'steps' => [
                ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => $from]]],
                ['id' => 'a', 'type' => 'action', 'action' => 'fake.set_priority', 'config' => ['priority' => $to]],
            ],
        ],
        ...$attributes,
    ]);
}

test('an automation does not trigger on the events its own run caused', function () {
    // Would loop forever if it saw its own change: any priority change sets it to urgent.
    $automation = Automation::factory()->enabled()->create([
        'trigger_type' => 'ticket.field_changed',
        'definition' => [
            'trigger' => ['type' => 'ticket.field_changed', 'config' => ['fields' => ['ticket.priority']]],
            'steps' => [['id' => 'a', 'type' => 'action', 'action' => 'fake.set_priority', 'config' => ['priority' => 'urgent']]],
        ],
    ]);
    $user = User::factory()->create();
    $this->workspace->users()->attach($user, ['role' => 'admin']);

    app(UpdateTicket::class)->handle($user, $this->ticket, ['priority' => 'low']);

    expect(AutomationRun::query()->where('automation_id', $automation->id)->count())->toBe(1)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT)
        ->and($this->ticket->events()->where('type', TicketEventType::PRIORITY_CHANGED)->count())->toBe(2);
});

test('the depth cap ends an automation ping-pong with a skipped run', function () {
    priorityRule('urgent', 'low');
    priorityRule('low', 'urgent');
    $user = User::factory()->create();
    $this->workspace->users()->attach($user, ['role' => 'admin']);

    app(UpdateTicket::class)->handle($user, $this->ticket, ['priority' => 'urgent']);

    $skipped = AutomationRun::query()->where('status', AutomationRunStatus::SKIPPED)->sole();
    $executed = AutomationRun::query()->where('status', '!=', AutomationRunStatus::SKIPPED)->where('status', '!=', AutomationRunStatus::RUNNING)->get();

    expect($skipped->depth)->toBe(RunAutomationsForEvent::MAX_DEPTH + 1)
        ->and($skipped->log[0])->toMatchArray(['type' => 'skipped'])
        ->and($skipped->log[0]['reason'])->toContain('more than 5 levels deep')
        ->and($skipped->parent_run_id)->not->toBeNull()
        ->and($executed->max('depth'))->toBe(RunAutomationsForEvent::MAX_DEPTH)
        ->and($executed->where('status', AutomationRunStatus::FAILED))->toBeEmpty()
        ->and(AutomationRun::query()->where('status', AutomationRunStatus::RUNNING)->exists())->toBeFalse();
});

test('a ticket gets at most 100 automation runs an hour, then runs are skipped with the reason', function () {
    Automation::factory()->enabled()->triggeredBy('ticket.created', 'fake.set_priority', ['priority' => 'normal'])->create();

    foreach (range(1, RunAutomationsForEvent::MAX_RUNS_PER_HOUR + 2) as $ignored) {
        $this->ticket->recordEvent(TicketEventType::CREATED, null);
    }

    $skipped = AutomationRun::query()->where('status', AutomationRunStatus::SKIPPED)->get();

    expect(AutomationRun::query()->where('status', '!=', AutomationRunStatus::SKIPPED)->count())->toBe(RunAutomationsForEvent::MAX_RUNS_PER_HOUR)
        ->and($skipped)->toHaveCount(2)
        ->and($skipped[0]->log[0]['reason'])->toBe('This ticket reached 100 automation runs in an hour.');

    // Another ticket has its own allowance.
    Ticket::factory()->create()->recordEvent(TicketEventType::CREATED, null);

    expect(AutomationRun::query()->where('status', AutomationRunStatus::SKIPPED)->count())->toBe(2);
});
