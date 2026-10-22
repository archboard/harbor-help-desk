<?php

use App\Actions\Tickets\CheckSlaTargets;
use App\Enums\DeskRole;
use App\Enums\NotificationType;
use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketSlaTarget;
use App\Models\Workspace;
use App\Notifications\TicketActivity;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
});

/**
 * An SLA clock started now with a 60 minute target that warns after 45.
 *
 * @param  array<string, mixed>  $attributes
 */
function slaClock(?Ticket $ticket = null, array $attributes = []): TicketSlaTarget
{
    return TicketSlaTarget::create([
        'ticket_id' => ($ticket ?? Ticket::factory()->create())->id,
        'metric' => SlaMetric::FIRST_RESPONSE,
        'target_minutes' => 60,
        'started_at' => now(),
        'warn_at' => now()->addMinutes(45),
        'due_at' => now()->addMinutes(60),
        ...$attributes,
    ]);
}

function slaEvents(Ticket $ticket): array
{
    return $ticket->events()->whereIn('type', [TicketEventType::SLA_WARNING, TicketEventType::SLA_BREACHED])->orderBy('id')->pluck('type')->all();
}

test('a target warns at the warning time and breaches at due, once each', function () {
    $target = slaClock();
    $ticket = $target->ticket;
    $check = app(CheckSlaTargets::class);

    expect($check->handle())->toBe(0);

    $this->travel(45)->minutes();
    expect($check->handle())->toBe(1)->and($check->handle())->toBe(0);
    expect(slaEvents($ticket))->toBe([TicketEventType::SLA_WARNING]);
    expect($target->fresh()->warned_at)->not->toBeNull()->and($target->fresh()->breached_at)->toBeNull();

    $this->travel(15)->minutes();
    expect($check->handle())->toBe(1)->and($check->handle())->toBe(0);
    expect(slaEvents($ticket))->toBe([TicketEventType::SLA_WARNING, TicketEventType::SLA_BREACHED]);
    expect($target->fresh()->breached_at)->not->toBeNull();
});

test('the events carry the metric and due time and no actor', function () {
    $target = slaClock();
    $this->travel(61)->minutes();

    app(CheckSlaTargets::class)->handle();

    $event = $target->ticket->events()->where('type', TicketEventType::SLA_BREACHED)->sole();
    expect($event->actor_type)->toBeNull()
        ->and($event->properties['metric'])->toBe('first_response')
        ->and($event->properties['due_at'])->toBe($target->due_at->toIso8601String())
        ->and($event->description())->toBe('First response SLA was breached.');
});

test('a target already past due is breached without a warning', function () {
    $target = slaClock();
    $this->travel(2)->hours();

    app(CheckSlaTargets::class)->handle();

    expect(slaEvents($target->ticket))->toBe([TicketEventType::SLA_BREACHED]);
});

test('paused, met, breached and deleted-ticket targets are ignored', function () {
    $paused = slaClock(null, ['paused_at' => now(), 'warn_at' => null, 'due_at' => null]);
    $met = slaClock(null, ['achieved_at' => now()]);
    $breached = slaClock(null, ['breached_at' => now()]);
    $deleted = slaClock();
    $deleted->ticket->delete();
    $this->travel(2)->hours();

    expect(app(CheckSlaTargets::class)->handle())->toBe(0);

    expect(TicketEvent::query()->whereIn('type', [TicketEventType::SLA_WARNING, TicketEventType::SLA_BREACHED])->count())->toBe(0);
    expect($paused->fresh()->breached_at)->toBeNull();
});

test('the limit caps a run', function () {
    slaClock();
    slaClock();
    $this->travel(2)->hours();

    expect(app(CheckSlaTargets::class)->handle(1))->toBe(1)
        ->and(app(CheckSlaTargets::class)->handle(1))->toBe(1)
        ->and(app(CheckSlaTargets::class)->handle(1))->toBe(0);
});

test('a warning goes to the assignees, or to the desk members when unassigned', function () {
    Notification::fake();
    $target = slaClock();
    $ticket = $target->ticket;
    $assignee = deskMember($this->workspace, $ticket->desk);
    $colleague = deskMember($this->workspace, $ticket->desk);
    $this->travel(46)->minutes();

    app(CheckSlaTargets::class)->handle();

    Notification::assertSentTo([$assignee, $colleague], TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::SLA_WARNING);

    Notification::fake();
    $second = slaClock();
    $second->ticket->assignees()->attach($assignee->id, ['workspace_id' => $this->workspace->id]);
    $assigneeOnDesk = deskMember($this->workspace, $second->ticket->desk);
    $second->ticket->desk->users()->attach($assignee->id, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $this->travel(46)->minutes();

    app(CheckSlaTargets::class)->handle();

    Notification::assertSentTo($assignee, TicketActivity::class);
    Notification::assertNotSentTo($assigneeOnDesk, TicketActivity::class);
});

test('a breach notifies the assignees and the desk leads', function () {
    Notification::fake();
    $target = slaClock();
    $ticket = $target->ticket;
    $assignee = deskMember($this->workspace, $ticket->desk);
    $lead = deskMember($this->workspace, $ticket->desk, DeskRole::LEAD);
    $bystander = deskMember($this->workspace, $ticket->desk);
    $ticket->assignees()->attach($assignee->id, ['workspace_id' => $this->workspace->id]);
    $this->travel(61)->minutes();

    app(CheckSlaTargets::class)->handle();

    Notification::assertSentTo([$assignee, $lead], TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::SLA_BREACHED);
    Notification::assertNotSentTo($bystander, TicketActivity::class);
    Notification::assertSentTimes(TicketActivity::class, 2);
});

describe('across workspaces', function () {
    beforeEach(function () {
        config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
        $this->other = Workspace::factory()->create(['subdomain' => 'other']);
        $this->otherTarget = $this->other->execute(fn () => slaClock());
        $this->ownTarget = slaClock();
        $this->travel(2)->hours();
    });

    test('the action only claims the current workspace\'s targets', function () {
        expect(app(CheckSlaTargets::class)->handle())->toBe(1);

        expect($this->ownTarget->fresh()->breached_at)->not->toBeNull();
        expect(TicketSlaTarget::withoutGlobalScope('workspace')->find($this->otherTarget->id)->breached_at)->toBeNull();
    });

    test('the command checks every workspace within that workspace', function () {
        $this->artisan('slas:check')->assertSuccessful();

        $events = TicketEvent::withoutGlobalScope('workspace')->where('type', TicketEventType::SLA_BREACHED)->get();
        expect($events)->toHaveCount(2)
            ->and($events->firstWhere('ticket_id', $this->ownTarget->ticket_id)->workspace_id)->toBe($this->workspace->id)
            ->and($events->firstWhere('ticket_id', $this->otherTarget->ticket_id)->workspace_id)->toBe($this->other->id);
    });

    test('a user in both workspaces is only notified about the ticket\'s workspace', function () {
        Notification::fake();
        $user = deskMember($this->workspace, $this->ownTarget->ticket->desk, DeskRole::LEAD);
        $this->other->users()->attach($user, ['role' => 'agent']);

        $this->artisan('slas:check')->assertSuccessful();

        Notification::assertSentToTimes($user, TicketActivity::class, 1);
        Notification::assertSentTo($user, TicketActivity::class, fn (TicketActivity $n) => $n->ticketEvent->ticket_id === $this->ownTarget->ticket_id);
    });
});
