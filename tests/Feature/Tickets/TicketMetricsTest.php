<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\RecomputeTicketMetrics;
use App\Actions\Tickets\UpdateTicket;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketMetric;
use App\Models\Workspace;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
    $this->start = now();
    $this->desk = Desk::factory()->create();
    $this->agent = deskMember($this->workspace, $this->desk);
});

/**
 * A ticket an agent opened, with an optional first message from the agent.
 */
function metricsTicket(array $input = []): Ticket
{
    return app(CreateTicket::class)->handle(test()->agent, test()->desk, [
        'subject' => 'Printer jam',
        'requester' => ['name' => 'Pat', 'email' => 'pat@example.com'],
        ...$input,
    ]);
}

function customerSays(Ticket $ticket, int $afterMinutes = 0): TicketComment
{
    test()->travel($afterMinutes)->minutes();

    return app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Hello?']);
}

function agentSays(Ticket $ticket, int $afterMinutes = 0, bool $internal = false): TicketComment
{
    test()->travel($afterMinutes)->minutes();

    return app(AddTicketComment::class)->handle(test()->agent, $ticket, ['body' => 'On it', 'is_internal' => $internal]);
}

function setStatus(Ticket $ticket, string $status, int $afterMinutes = 0): void
{
    test()->travel($afterMinutes)->minutes();
    app(UpdateTicket::class)->handle(test()->agent, $ticket->fresh(), ['status' => $status]);
}

test('a ticket an agent opened without a customer message has no first response', function () {
    $ticket = metricsTicket(['body' => 'We noticed your printer is down']);
    $reply = agentSays($ticket, 10);

    expect($ticket->metric)
        ->first_responded_at->toBeNull()
        ->first_response_minutes->toBeNull()
        ->and($reply->fresh()->response_minutes)->toBeNull();
});

test('each reply times the wait since the earliest unanswered customer comment, ignoring internal notes', function () {
    $ticket = metricsTicket();
    customerSays($ticket);
    $note = agentSays($ticket, 5, internal: true);
    $first = agentSays($ticket, 5);
    customerSays($ticket, 5);
    customerSays($ticket, 5);
    $second = agentSays($ticket, 20);
    $followUp = agentSays($ticket, 5);

    expect($ticket->metric)
        ->first_responded_at->equalTo($this->start->addMinutes(10))->toBeTrue()
        ->first_responder_id->toBe($this->agent->id)
        ->first_response_minutes->toBe(10)
        ->first_response_business_minutes->toBe(10)
        ->and($note->fresh()->response_minutes)->toBeNull()
        ->and($first->fresh()->response_minutes)->toBe(10)
        ->and($second->fresh()->response_minutes)->toBe(25)
        ->and($followUp->fresh()->response_minutes)->toBeNull();
});

test('resolution runs to when the ticket last became resolved and a reopen clears it', function () {
    $ticket = metricsTicket();

    setStatus($ticket, 'solved', 30);

    expect($ticket->metric->fresh())->resolution_minutes->toBe(30)->reopen_count->toBe(0);

    customerSays($ticket->fresh(), 10);

    expect($ticket->metric->fresh())->resolved_at->toBeNull()->resolution_minutes->toBeNull()->reopen_count->toBe(1);

    setStatus($ticket, 'solved', 20);
    setStatus($ticket, 'closed', 10);

    expect($ticket->metric->fresh())
        ->resolved_at->equalTo($this->start->addMinutes(60))->toBeTrue()
        ->resolution_minutes->toBe(60)
        ->reopen_count->toBe(1);
});

test('a ticket created already solved counts as resolved at creation', function () {
    expect(metricsTicket(['status' => 'solved'])->metric)
        ->resolved_at->equalTo($this->start)->toBeTrue()
        ->resolution_minutes->toBe(0);
});

test('deleting the first reply moves first response to the next one', function () {
    $ticket = metricsTicket();
    customerSays($ticket);
    $first = agentSays($ticket, 10);
    $second = agentSays($ticket, 10);

    app(DeleteTicketComment::class)->handle($this->agent, $first);

    expect($ticket->metric->fresh())
        ->first_response_minutes->toBe(20)
        ->and($first->fresh()->response_minutes)->toBeNull()
        ->and($second->fresh()->response_minutes)->toBe(20);
});

test('business minutes follow the effective schedule and equal calendar minutes without one', function (bool $withSchedule, int $businessMinutes) {
    // Monday 16:00 UTC, an hour before a 09:00–17:00 weekday schedule closes.
    $this->travelTo(now()->next('Monday')->setTime(16, 0));

    if ($withSchedule) {
        $this->desk->update(['sla_policy_id' => SlaPolicy::factory()->create(['business_schedule_id' => BusinessSchedule::factory()->create()->id])->id]);
    }

    $ticket = metricsTicket();
    customerSays($ticket);
    agentSays($ticket, 18 * 60);
    setStatus($ticket, 'solved');

    expect($ticket->metric)
        ->first_response_minutes->toBe(18 * 60)
        ->first_response_business_minutes->toBe($businessMinutes)
        ->resolution_minutes->toBe(18 * 60)
        ->resolution_business_minutes->toBe($businessMinutes);
})->with([
    'business hours' => [true, 120],
    '24/7' => [false, 18 * 60],
]);

describe('across workspaces', function () {
    beforeEach(function () {
        config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
        $this->own = metricsTicket();
        customerSays($this->own);
        agentSays($this->own, 10);

        $this->other = Workspace::factory()->create(['subdomain' => 'other']);
        $this->otherTicket = $this->other->execute(function () {
            $desk = Desk::factory()->create();
            $agent = deskMember($this->other, $desk);
            $ticket = app(CreateTicket::class)->handle($agent, $desk, ['subject' => 'Other', 'requester' => ['name' => 'Sam', 'email' => 'sam@example.com']]);
            app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Hi']);
            $this->travel(30)->minutes();
            app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Hello']);

            return $ticket;
        });

        TicketMetric::withoutGlobalScope('workspace')->delete();
        TicketComment::withoutGlobalScope('workspace')->update(['response_minutes' => null, 'response_business_minutes' => null]);
    });

    test('the command backfills every workspace\'s tickets within that workspace', function () {
        $this->artisan('tickets:recompute-metrics')->assertSuccessful();

        $metrics = TicketMetric::withoutGlobalScope('workspace')->get()->keyBy('ticket_id');

        expect($metrics)->toHaveCount(2)
            ->and($metrics[$this->own->id])->workspace_id->toBe($this->workspace->id)->first_response_minutes->toBe(10)
            ->and($metrics[$this->otherTicket->id])->workspace_id->toBe($this->other->id)->first_response_minutes->toBe(30)
            ->and(TicketComment::withoutGlobalScope('workspace')->whereNotNull('response_minutes')->count())->toBe(2);
    });

    test('recomputing a ticket never touches another workspace\'s rows', function () {
        app(RecomputeTicketMetrics::class)->handle($this->own);

        expect(TicketMetric::withoutGlobalScope('workspace')->sole())->ticket_id->toBe($this->own->id)
            ->and(TicketComment::withoutGlobalScope('workspace')->where('workspace_id', $this->other->id)->whereNotNull('response_minutes')->exists())->toBeFalse();
    });
});
