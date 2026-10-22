<?php

use App\Actions\Automations\ResumeAutomationRun;
use App\Actions\Tickets\DeleteTicket;
use App\Automations\RunCausation;
use App\Enums\AutomationRunStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\BusinessSchedule;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00 UTC'));
    $this->ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
});

function delayStep(array $delay = [], array $cancelOn = []): array
{
    return ['id' => 'd', 'type' => 'delay', 'cancel_on' => $cancelOn, 'for' => ['amount' => 1, 'unit' => 'hours'], ...$delay];
}

function priorityStep(string $id = 'a', string $priority = 'urgent'): array
{
    return ['id' => $id, 'type' => 'action', 'action' => 'set_field', 'config' => ['field' => 'ticket.priority', 'value' => $priority]];
}

function priorityIs(string $priority): array
{
    return ['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => $priority]]];
}

/**
 * Creates an enabled ticket.created automation with the steps and fires it for the ticket.
 *
 * @param  list<array<string, mixed>>  $steps
 * @param  array<string, mixed>  $attributes
 */
function startDelayed(array $steps, ?Ticket $ticket = null, array $attributes = []): AutomationRun
{
    $automation = Automation::factory()->enabled()->create([
        'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => $steps],
        ...$attributes,
    ]);

    ($ticket ?? test()->ticket)->recordEvent(TicketEventType::CREATED, null);

    return AutomationRun::query()->where('automation_id', $automation->id)->latest('id')->firstOrFail();
}

function resumeRuns(): void
{
    test()->artisan('automations:resume')->assertSuccessful();
}

test('a for delay pauses the run and the later action runs after the wait', function () {
    $run = startDelayed([delayStep(), priorityStep()]);

    expect($run->status)->toBe(AutomationRunStatus::WAITING)
        ->and($run->resume_at->toIso8601String())->toBe(now()->addHour()->toIso8601String())
        ->and($run->state['resume'])->toBe([1])
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);

    $this->travel(30)->minutes();
    resumeRuns();
    expect($run->refresh()->status)->toBe(AutomationRunStatus::WAITING);

    $this->travel(31)->minutes();
    resumeRuns();

    expect($run->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->resume_at)->toBeNull()
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT)
        ->and($this->ticket->events()->where('automation_run_id', $run->id)->count())->toBe(1);
});

test('a business-time delay skips the closed hours', function () {
    $schedule = BusinessSchedule::factory()->create();
    $this->travelTo(CarbonImmutable::parse('2026-10-23 16:00:00 UTC')); // Friday

    $run = startDelayed([delayStep(['for' => ['amount' => 2, 'unit' => 'hours', 'business_schedule_id' => $schedule->id]]), priorityStep()]);

    expect($run->resume_at->toIso8601String())->toBe('2026-10-26T10:00:00+00:00');
});

test('an until delay waits for a date field shifted by the offset', function () {
    $this->ticket->update(['due_at' => now()->addHours(5)]);

    $run = startDelayed([delayStep(['for' => null, 'until' => ['field' => 'ticket.due_at', 'offset' => -60]]), priorityStep()]);

    expect($run->status)->toBe(AutomationRunStatus::WAITING)
        ->and($run->resume_at->toIso8601String())->toBe(now()->addHours(4)->toIso8601String());
});

test('an until delay on an empty date fails the run with a safe message', function () {
    $run = startDelayed([delayStep(['for' => null, 'until' => ['field' => 'ticket.due_at', 'offset' => 0]]), priorityStep()]);

    expect($run->status)->toBe(AutomationRunStatus::FAILED)
        ->and($run->error)->toBe('The delay can not wait until a date that is not set.')
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('a delay that is over already does not pause', function () {
    $this->ticket->update(['due_at' => now()->addMinutes(30)]);

    $run = startDelayed([delayStep(['for' => null, 'until' => ['field' => 'ticket.due_at', 'offset' => -60]]), priorityStep()]);

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
});

test('conditions after a delay see the ticket as it is when the run resumes', function () {
    $run = startDelayed([delayStep(), priorityIs('normal'), priorityStep()]);

    $this->ticket->update(['priority' => TicketPriority::LOW]);
    $this->travel(2)->hours();
    resumeRuns();

    expect($run->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->state['matched'])->toBeFalse()
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::LOW);
});

test('a requester comment cancels a run waiting on comments, but the run\'s own events do not', function () {
    $run = startDelayed([delayStep(cancelOn: ['ticket.comment_added']), priorityStep()]);

    app(RunCausation::class)->during($run->id, fn () => $this->ticket->recordEvent(TicketEventType::COMMENT_ADDED, null));
    expect($run->refresh()->status)->toBe(AutomationRunStatus::WAITING);

    $this->ticket->recordEvent(TicketEventType::STATUS_CHANGED, null, ['from' => 'open', 'to' => 'pending']);
    expect($run->refresh()->status)->toBe(AutomationRunStatus::WAITING);

    $this->ticket->recordEvent(TicketEventType::COMMENT_ADDED, Contact::factory()->create());

    expect($run->refresh()->status)->toBe(AutomationRunStatus::CANCELLED)
        ->and($run->resume_at)->toBeNull()
        ->and(collect($run->log)->last()['type'])->toBe('cancelled');

    $this->travel(2)->hours();
    resumeRuns();
    expect($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('a resumed run follows its snapshot, not the edited automation', function () {
    $run = startDelayed([delayStep(), priorityStep(priority: 'urgent')]);

    Automation::query()->sole()->update(['definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => [priorityStep(priority: 'low')]]]);
    $this->travel(2)->hours();
    resumeRuns();

    expect($run->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::URGENT);
});

test('a run is cancelled on resume when its automation is disabled or no longer fits the ticket\'s desk', function (string $change) {
    $run = startDelayed([delayStep(), priorityStep()]);
    $automation = Automation::query()->sole();

    match ($change) {
        'disabled' => $automation->update(['enabled' => false]),
        'desk scope' => [$automation->update(['desk_id' => $this->ticket->desk_id]), $this->ticket->update(['desk_id' => Desk::factory()->create()->id])],
    };
    $this->travel(2)->hours();
    resumeRuns();

    expect($run->refresh()->status)->toBe(AutomationRunStatus::CANCELLED)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
})->with(['disabled', 'desk scope']);

test('deleting the automation removes its waiting runs, so nothing resumes', function () {
    startDelayed([delayStep(), priorityStep()]);

    Automation::query()->sole()->delete();
    $this->travel(2)->hours();
    resumeRuns();

    expect(AutomationRun::query()->count())->toBe(0)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::NORMAL);
});

test('deleting the ticket cancels its waiting runs', function () {
    $run = startDelayed([delayStep(), priorityStep()]);
    $other = startDelayed([delayStep(), priorityStep()], Ticket::factory()->create());

    $admin = User::factory()->create();
    $this->workspace->users()->attach($admin, ['role' => 'admin']);
    app(DeleteTicket::class)->handle($admin, $this->ticket);

    expect($run->refresh()->status)->toBe(AutomationRunStatus::CANCELLED)
        ->and($other->refresh()->status)->toBe(AutomationRunStatus::WAITING);
});

test('a delay inside a paths branch resumes in the branch and then after the paths step', function () {
    $run = startDelayed([
        ['id' => 'p', 'type' => 'paths', 'branches' => [
            ['name' => 'Normal', 'condition' => ['match' => 'all', 'rules' => priorityIs('normal')['rules']], 'steps' => [delayStep(), priorityStep('a1', 'high')]],
        ], 'else' => []],
        ['id' => 'note', 'type' => 'action', 'action' => 'add_note', 'config' => ['body' => 'After paths']],
    ]);

    expect($run->status)->toBe(AutomationRunStatus::WAITING)
        ->and($run->state['resume'])->toBe([0, 0, 1])
        ->and($this->ticket->comments()->count())->toBe(0);

    $this->travel(2)->hours();
    resumeRuns();

    expect($run->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->refresh()->priority)->toBe(TicketPriority::HIGH)
        ->and($this->ticket->comments()->where('body', 'After paths')->count())->toBe(1);
});

test('a run that was claimed already is not resumed twice', function () {
    $run = startDelayed([delayStep(), ['id' => 'note', 'type' => 'action', 'action' => 'add_note', 'config' => ['body' => 'Once']]]);
    $this->travel(2)->hours();

    resumeRuns();
    resumeRuns();
    app(ResumeAutomationRun::class)->handle($run);

    expect($run->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($this->ticket->comments()->where('body', 'Once')->count())->toBe(1);
});

test('resuming runs each workspace in its own workspace and events never cancel another workspace\'s runs', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $acme = $this->workspace;
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $steps = [delayStep(cancelOn: ['ticket.comment_added']), priorityStep()];

    $acmeRun = startDelayed($steps);
    [$otherTicket, $otherRun] = $other->execute(function () use ($steps) {
        $ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);

        return [$ticket, startDelayed($steps, $ticket)];
    });

    // A comment in acme cancels acme's run only.
    $acmeCommentTicket = $this->ticket;
    $acmeCommentTicket->recordEvent(TicketEventType::COMMENT_ADDED, Contact::factory()->create());
    expect($acmeRun->refresh()->status)->toBe(AutomationRunStatus::CANCELLED)
        ->and($other->execute(fn () => $otherRun->refresh()->status))->toBe(AutomationRunStatus::WAITING);

    $acmeTicket2 = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
    $acmeRun2 = startDelayed([delayStep(), priorityStep()], $acmeTicket2, ['name' => 'Second']);

    $this->travel(2)->hours();
    resumeRuns();

    expect($acmeRun2->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($acmeTicket2->refresh()->priority)->toBe(TicketPriority::URGENT)
        ->and($acmeCommentTicket->refresh()->priority)->toBe(TicketPriority::NORMAL);

    $other->execute(function () use ($otherRun, $otherTicket) {
        expect($otherRun->refresh()->status)->toBe(AutomationRunStatus::COMPLETED)
            ->and($otherRun->workspace_id)->toBe(Workspace::current()->id)
            ->and($otherTicket->refresh()->priority)->toBe(TicketPriority::URGENT);
    });
});
