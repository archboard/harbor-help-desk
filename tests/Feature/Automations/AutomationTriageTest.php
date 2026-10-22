<?php

use App\Actions\Tickets\MoveTicket;
use App\Automations\Actions\Assign;
use App\Automations\AutomationRegistry;
use App\Automations\Triggers\TicketCreated;
use App\Enums\AssignmentStrategy;
use App\Enums\DeskRole;
use App\Enums\NotificationType;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Events\TicketTriaged;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TicketActivity;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InWorkspace;
use Tests\Fakes\Automations\CrashAction;

uses(InWorkspace::class);

beforeEach(function () {
    Notification::fake();
    app()->instance(AutomationRegistry::class, new AutomationRegistry([TicketCreated::class], [Assign::class, CrashAction::class]));

    $this->desk = Desk::factory()->create(['assignment_strategy' => AssignmentStrategy::ROUND_ROBIN]);
    $this->ann = deskMember($this->workspace, $this->desk);
    $this->bob = deskMember($this->workspace, $this->desk);
});

/**
 * @param  list<array<string, mixed>>  $steps
 */
function triageAutomation(array $steps, int $position = 1): Automation
{
    return Automation::factory()->enabled()->create([
        'trigger_type' => 'ticket.created',
        'position' => $position,
        'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => $steps],
    ]);
}

function assignTo(User $user): array
{
    return ['id' => 'a1', 'type' => 'action', 'action' => 'assign', 'config' => ['mode' => 'replace', 'user_ids' => [$user->id]]];
}

function newTicketOn(Desk $desk): Ticket
{
    $ticket = Ticket::factory()->for($desk)->create();
    $ticket->recordEvent(TicketEventType::CREATED, null);

    return $ticket;
}

function assignedIds(Ticket $ticket): array
{
    return $ticket->assignees()->pluck('users.id')->all();
}

function rotationPicks(Desk $desk): int
{
    return $desk->users()->wherePivotNotNull('last_assigned_at')->count();
}

function sentNewTicketTo(User $user, ?Ticket $ticket = null): bool
{
    return Notification::sent($user, TicketActivity::class)
        ->contains(fn (TicketActivity $n) => $n->type === NotificationType::NEW_TICKET && ($ticket === null || $n->ticketEvent->ticket_id === $ticket->id));
}

test('an automation that assigns wins over the desk rotation', function () {
    triageAutomation([assignTo($this->bob)]);

    $ticket = newTicketOn($this->desk);

    expect(assignedIds($ticket))->toBe([$this->bob->id])
        ->and(rotationPicks($this->desk))->toBe(0)
        ->and(sentNewTicketTo($this->ann))->toBeFalse()
        ->and(sentNewTicketTo($this->bob))->toBeFalse();
    Notification::assertSentTo($this->bob, TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::ASSIGNED);
});

test('round robin applies when no automation assigns', function (string $setup) {
    match ($setup) {
        'no automations' => null,
        'no match' => triageAutomation([assignTo($this->bob)]) && Automation::query()->update(['enabled' => false]),
        'stop' => triageAutomation([['id' => 's1', 'type' => 'stop']]),
        'failure' => triageAutomation([['id' => 'a1', 'type' => 'action', 'action' => 'fake.crash', 'config' => []]]),
    };
    Exceptions::fake();

    $ticket = newTicketOn($this->desk);

    expect(assignedIds($ticket))->toHaveCount(1)
        ->and(rotationPicks($this->desk))->toBe(1);
})->with(['no automations', 'no match', 'stop', 'failure']);

test('round robin applies after a stop that skips the later automations', function () {
    triageAutomation([['id' => 's1', 'type' => 'stop']], position: 1);
    triageAutomation([assignTo($this->bob)], position: 2);

    expect(assignedIds(newTicketOn($this->desk)))->toHaveCount(1)
        ->and(rotationPicks($this->desk))->toBe(1);
});

test('desk members hear about a new ticket only when it is still unassigned after triage', function () {
    $manual = Desk::factory()->create();
    $dan = deskMember($this->workspace, $manual);

    $unassigned = newTicketOn($manual);
    $assigned = newTicketOn($this->desk);

    expect(sentNewTicketTo($dan, $unassigned))->toBeTrue()
        ->and(assignedIds($assigned))->toHaveCount(1)
        ->and(sentNewTicketTo($this->ann, $assigned))->toBeFalse()
        ->and(sentNewTicketTo($this->bob, $assigned))->toBeFalse();
});

test('a desk nobody is eligible on still announces the new ticket', function () {
    $this->desk->users()->updateExistingPivot($this->ann->id, ['in_rotation' => false]);
    $this->desk->users()->updateExistingPivot($this->bob->id, ['in_rotation' => false]);

    $ticket = newTicketOn($this->desk);

    expect(assignedIds($ticket))->toBeEmpty()
        ->and(sentNewTicketTo($this->ann, $ticket))->toBeTrue()
        ->and(sentNewTicketTo($this->bob, $ticket))->toBeTrue();
});

test('moving a ticket to a round robin desk assigns it without announcing a new ticket', function () {
    $manual = Desk::factory()->create();
    $mover = deskMember($this->workspace, $manual);
    $this->desk->users()->attach($mover, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $ticket = Ticket::factory()->for($manual)->create();

    app(MoveTicket::class)->handle($mover, $ticket, $this->desk);

    expect(assignedIds($ticket))->toHaveCount(1);
    foreach ([$this->ann, $this->bob, $mover] as $user) {
        expect(sentNewTicketTo($user))->toBeFalse();
    }
});

test('TicketTriaged is dispatched once per created and desk changed event only', function () {
    Event::fake([TicketTriaged::class]);
    $manual = Desk::factory()->create();
    $mover = deskMember($this->workspace, $manual);
    $this->desk->users()->attach($mover, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);

    $ticket = newTicketOn($manual);
    Event::assertDispatchedTimes(TicketTriaged::class, 1);

    $ticket->recordEvent(TicketEventType::TAG_ADDED, null, ['tag' => 'x']);
    Event::assertDispatchedTimes(TicketTriaged::class, 1);

    app(MoveTicket::class)->handle($mover, $ticket, $this->desk);
    Event::assertDispatchedTimes(TicketTriaged::class, 2);
    Event::assertDispatched(TicketTriaged::class, fn (TicketTriaged $e) => $e->ticketEvent->type === TicketEventType::DESK_CHANGED);
});

test('triage only reaches the current workspace\'s desk members', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $acme = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $acmeDesk = Desk::factory()->create(['assignment_strategy' => AssignmentStrategy::ROUND_ROBIN]);
    $acmeAgent = deskMember($acme, $acmeDesk);

    // A member of both workspaces: only in rotation in the other one.
    $shared = deskMember($acme, $acmeDesk);
    $acmeDesk->users()->updateExistingPivot($shared->id, ['in_rotation' => false]);
    $other->users()->attach($shared, ['role' => WorkspaceRole::AGENT]);
    [$otherDesk, $otherAgent] = $other->execute(function () use ($other, $shared) {
        $desk = Desk::factory()->create(['assignment_strategy' => AssignmentStrategy::ROUND_ROBIN]);
        $desk->users()->attach($shared, ['role' => DeskRole::AGENT, 'workspace_id' => $other->id]);

        return [$desk, deskMember($other, $desk)];
    });
    $manual = Desk::factory()->create();
    $manual->users()->attach($shared, ['role' => DeskRole::AGENT, 'workspace_id' => $acme->id]);

    $assigned = newTicketOn($acmeDesk);
    $announced = newTicketOn($manual);

    expect(assignedIds($assigned))->toBe([$acmeAgent->id])
        ->and(sentNewTicketTo($acmeAgent, $assigned))->toBeFalse()
        ->and(sentNewTicketTo($shared, $announced))->toBeTrue()
        ->and(sentNewTicketTo($otherAgent))->toBeFalse()
        ->and(Notification::sent($shared, TicketActivity::class)->filter(fn (TicketActivity $n) => $n->type === NotificationType::NEW_TICKET))->toHaveCount(1)
        ->and($other->execute(fn () => $otherDesk->users()->wherePivotNotNull('last_assigned_at')->count()))->toBe(0);
});
