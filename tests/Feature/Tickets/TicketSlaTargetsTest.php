<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\MoveTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\DeskRole;
use App\Enums\SlaMetric;
use App\Models\Desk;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketSlaTarget;
use App\Models\Workspace;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->travelTo(now()->startOfMinute());
    $this->start = now();
    $this->policy = SlaPolicy::factory()->create();
    $this->desk = Desk::factory()->create(['sla_policy_id' => $this->policy->id]);
    $this->agent = deskMember($this->workspace, $this->desk);
});

function openTicket(array $input = []): Ticket
{
    return app(CreateTicket::class)->handle(test()->agent, test()->desk, [
        'subject' => 'Printer jam',
        'requester' => ['name' => 'Pat', 'email' => 'pat@example.com'],
        ...$input,
    ]);
}

function slaTarget(Ticket $ticket, SlaMetric $metric): ?TicketSlaTarget
{
    return $ticket->slaTargets()->where('metric', $metric)->first();
}

test('targets are created from the desk policy and the ticket priority', function () {
    $ticket = openTicket(['priority' => 'urgent']);

    expect(slaTarget($ticket, SlaMetric::FIRST_RESPONSE))
        ->target_minutes->toBe(60)
        ->due_at->equalTo($this->start->addMinutes(60))->toBeTrue()
        ->warn_at->equalTo($this->start->addMinutes(45))->toBeTrue()
        ->and(slaTarget($ticket, SlaMetric::RESOLUTION)->target_minutes)->toBe(480);
});

test('a ticket has no targets without a policy or for a priority the policy leaves out', function () {
    $this->desk->update(['sla_policy_id' => null]);

    expect(openTicket()->slaTargets()->count())->toBe(0);

    $this->desk->update(['sla_policy_id' => $this->policy->id]);

    expect(openTicket(['priority' => 'low'])->slaTargets()->count())->toBe(0);
});

test('only the first public agent reply meets first response', function () {
    $ticket = openTicket();
    $comment = app(AddTicketComment::class);

    $this->travel(5)->minutes();
    $comment->handle($ticket->requester, $ticket, ['body' => 'Still broken']);
    $comment->handle($this->agent, $ticket, ['body' => 'Internal', 'is_internal' => true]);

    expect(slaTarget($ticket, SlaMetric::FIRST_RESPONSE)->achieved_at)->toBeNull();

    $this->travel(5)->minutes();
    $comment->handle($this->agent, $ticket, ['body' => 'On it']);

    expect(slaTarget($ticket, SlaMetric::FIRST_RESPONSE)->achieved_at->equalTo($this->start->addMinutes(10)))->toBeTrue();

    $this->travel(5)->minutes();
    $comment->handle($this->agent, $ticket, ['body' => 'Fixed']);

    expect(slaTarget($ticket, SlaMetric::FIRST_RESPONSE)->achieved_at->equalTo($this->start->addMinutes(10)))->toBeTrue();
});

test('pending pauses resolution and resuming adds the paused time', function () {
    $ticket = openTicket();
    $update = app(UpdateTicket::class);

    $this->travel(60)->minutes();
    $update->handle($this->agent, $ticket, ['status' => 'pending']);
    $paused = slaTarget($ticket, SlaMetric::RESOLUTION);

    expect($paused->paused_at)->not->toBeNull()
        ->and($paused->due_at)->toBeNull()
        ->and(slaTarget($ticket, SlaMetric::FIRST_RESPONSE)->paused_at)->toBeNull();

    $this->travel(120)->minutes();
    $update->handle($this->agent, $ticket, ['status' => 'open']);

    expect(slaTarget($ticket, SlaMetric::RESOLUTION))
        ->paused_at->toBeNull()
        ->paused_business_minutes->toBe(120)
        ->due_at->equalTo($this->start->addMinutes(2880 + 120))->toBeTrue();
});

test('solving meets resolution and reopening resumes it with the time already used', function () {
    $ticket = openTicket();
    $update = app(UpdateTicket::class);

    $this->travel(60)->minutes();
    $update->handle($this->agent, $ticket, ['status' => 'solved']);

    expect(slaTarget($ticket, SlaMetric::RESOLUTION)->achieved_at->equalTo($this->start->addMinutes(60)))->toBeTrue();

    $this->travel(30)->minutes();
    $update->handle($this->agent, $ticket, ['status' => 'open']);

    expect(slaTarget($ticket, SlaMetric::RESOLUTION))
        ->achieved_at->toBeNull()
        ->paused_business_minutes->toBe(30)
        ->due_at->equalTo($this->start->addMinutes(2880 + 30))->toBeTrue();
});

test('a priority change recalculates the targets and a breach stays', function () {
    $ticket = openTicket();
    slaTarget($ticket, SlaMetric::FIRST_RESPONSE)->update(['breached_at' => now()]);

    app(UpdateTicket::class)->handle($this->agent, $ticket, ['priority' => 'urgent']);

    expect(slaTarget($ticket, SlaMetric::FIRST_RESPONSE))
        ->target_minutes->toBe(60)
        ->breached_at->not->toBeNull()
        ->and(slaTarget($ticket, SlaMetric::RESOLUTION))
        ->target_minutes->toBe(480)
        ->due_at->equalTo($this->start->addMinutes(480))->toBeTrue();

    app(UpdateTicket::class)->handle($this->agent, $ticket, ['priority' => 'low']);

    expect(slaTarget($ticket, SlaMetric::FIRST_RESPONSE))->not->toBeNull()
        ->and(slaTarget($ticket, SlaMetric::RESOLUTION))->toBeNull();
});

test('moving to a desk without a policy deletes the unmet targets', function () {
    $ticket = openTicket();
    app(AddTicketComment::class)->handle($this->agent, $ticket, ['body' => 'On it']);
    $other = Desk::factory()->create();
    $this->agent->desks()->attach($other, ['workspace_id' => $this->workspace->id, 'role' => DeskRole::AGENT]);

    app(MoveTicket::class)->handle($this->agent, $ticket, $other);

    expect($ticket->slaTargets()->pluck('metric')->all())->toBe([SlaMetric::FIRST_RESPONSE]);
});

test('moving to a desk with a policy starts the missing targets from creation', function () {
    $this->desk->update(['sla_policy_id' => null]);
    $ticket = openTicket();
    $other = Desk::factory()->create(['sla_policy_id' => $this->policy->id]);
    $this->agent->desks()->attach($other, ['workspace_id' => $this->workspace->id, 'role' => DeskRole::AGENT]);

    $this->travel(10)->minutes();
    app(MoveTicket::class)->handle($this->agent, $ticket, $other);

    expect(slaTarget($ticket, SlaMetric::RESOLUTION)->due_at->equalTo($this->start->addMinutes(2880)))->toBeTrue();
});

test('targets are isolated between workspaces', function () {
    $ticket = openTicket();
    $foreign = Workspace::factory()->create()->execute(function () {
        $desk = Desk::factory()->create(['sla_policy_id' => SlaPolicy::factory()->create()->id]);
        $agent = deskMember(Workspace::current(), $desk);
        $ticket = app(CreateTicket::class)->handle($agent, $desk, ['subject' => 'Theirs', 'requester' => ['name' => 'Al', 'email' => 'al@example.com']]);
        app(UpdateTicket::class)->handle($agent, $ticket, ['status' => 'pending']);

        return $ticket;
    });

    app(UpdateTicket::class)->handle($this->agent, $ticket, ['status' => 'solved']);

    expect(TicketSlaTarget::count())->toBe(2)
        ->and(Workspace::find($foreign->workspace_id)->execute(fn () => TicketSlaTarget::where('metric', SlaMetric::RESOLUTION)->first()))
        ->paused_at->not->toBeNull()
        ->achieved_at->toBeNull();
});
