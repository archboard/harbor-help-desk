<?php

use App\Actions\Reports\BuildAgentsReport;
use App\Actions\Reports\BuildOverviewReport;
use App\Actions\Reports\BuildResolutionReport;
use App\Actions\Reports\BuildResponsesReport;
use App\Actions\Reports\BuildSatisfactionReport;
use App\Actions\Reports\BuildSlaReport;
use App\Data\Reports\KpiData;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketMetric;
use App\Models\TicketSlaTarget;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-16 12:00:00', 'UTC'));
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->otherDesk = Desk::factory()->create(['name' => 'Billing']);
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->week = ['from' => '2026-03-10', 'to' => '2026-03-16'];
});

/**
 * A ticket on the agent's desk unless the attributes say otherwise, with metrics when given.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>|null  $metric
 */
function reportTicket(string $createdAt, array $attributes = [], ?array $metric = null): Ticket
{
    $ticket = Ticket::factory()->create(['desk_id' => test()->desk->id, 'created_at' => $createdAt, ...$attributes]);

    if ($metric !== null) {
        TicketMetric::factory()->for($ticket)->create($metric);
    }

    return $ticket;
}

function statusEvent(Ticket $ticket, string $from, string $to, string $at, ?User $actor = null): void
{
    (new TicketEvent([
        'ticket_id' => $ticket->id,
        'type' => TicketEventType::STATUS_CHANGED,
        'properties' => ['from' => $from, 'to' => $to],
        'actor_type' => $actor?->getMorphClass(),
        'actor_id' => $actor?->id,
        'created_at' => $at,
    ]))->save();
}

describe('filters', function () {
    test('default to the last 30 days in the viewer\'s timezone, on a calendar basis', function () {
        $this->agent->update(['timezone' => 'Pacific/Auckland']);

        expect(app(BuildOverviewReport::class)->handle($this->agent, [])->period)
            ->from->toBe('2026-02-16')
            ->to->toBe('2026-03-17')
            ->previousFrom->toBe('2026-01-17')
            ->previousTo->toBe('2026-02-15')
            ->bucket->toBe('day')
            ->basis->value->toBe('calendar');
    });

    test('are bucketed by week, from Monday, past 90 days', function () {
        $report = app(BuildOverviewReport::class)->handle($this->agent, ['from' => '2026-01-01', 'to' => '2026-04-30']);

        expect($report->period->bucket)->toBe('week')
            ->and($report->volume[0]->date)->toBe('2025-12-29')
            ->and($report->volume)->toHaveCount(18);
    });

    test('reject a reversed range, more than 366 days and desks the viewer can\'t see', function (array $input, string $error) {
        expect(fn () => app(BuildOverviewReport::class)->handle($this->agent, $input))
            ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($error));
    })->with([
        'reversed' => [['from' => '2026-03-10', 'to' => '2026-03-01'], 'to'],
        'too long' => [['from' => '2025-01-01', 'to' => '2026-01-02'], 'to'],
        'invisible desk' => [fn () => ['desk_ids' => [test()->otherDesk->id]], 'desk_ids.0'],
    ]);

    test('let admins pick any desk', function () {
        reportTicket('2026-03-12 10:00', ['desk_id' => $this->otherDesk->id]);
        reportTicket('2026-03-12 10:00');

        expect(app(BuildOverviewReport::class)->handle($this->admin, [...$this->week, 'desk_ids' => [$this->otherDesk->id]])->created->value)->toBe(1);
    });
});

describe('overview', function () {
    beforeEach(function () {
        $this->open = reportTicket('2026-03-10 09:00', ['source' => 'email', 'status' => 'open']);
        $solved = reportTicket('2026-03-12 10:00', ['status' => 'solved'], ['resolved_at' => '2026-03-13 10:00']);
        reportTicket('2026-03-12 11:00', ['status' => 'pending']);
        reportTicket('2026-03-05 10:00', ['status' => 'solved'], ['resolved_at' => '2026-03-06 10:00']);
        reportTicket('2026-03-12 12:00', ['status' => 'solved'], ['resolved_at' => '2026-03-13 12:00'])->delete();
        reportTicket('2026-03-12 13:00', ['desk_id' => $this->otherDesk->id, 'status' => 'open'], ['resolved_at' => '2026-03-14 10:00']);

        statusEvent($this->open, 'solved', 'open', '2026-03-14 09:00');
        statusEvent($solved, 'solved', 'closed', '2026-03-14 09:00');
    });

    test('counts an agent\'s desks against the previous period, leaving deleted tickets out', function () {
        $report = app(BuildOverviewReport::class)->handle($this->agent, $this->week);

        expect($report->created)->toEqual(new KpiData(3, 1))
            ->and($report->resolved)->toEqual(new KpiData(1, 1))
            ->and($report->reopened)->toEqual(new KpiData(1, 0))
            ->and(collect($report->backlog)->pluck('count', 'key')->all())->toBe(['open' => 1, 'pending' => 1, 'on_hold' => 0])
            ->and(collect($report->bySource)->pluck('count', 'key')->all())->toBe(['web' => 2, 'email' => 1])
            ->and(collect($report->byDesk)->pluck('count', 'key')->all())->toBe([$this->desk->id => 3])
            ->and($report->volume)->toHaveCount(7)
            ->and($report->volume[0]->values)->toBe(['created' => 1, 'resolved' => 0])
            ->and($report->volume[2]->values)->toBe(['created' => 2, 'resolved' => 0])
            ->and($report->volume[3]->values)->toBe(['created' => 0, 'resolved' => 1]);
    });

    test('counts every desk for an admin', function () {
        $report = app(BuildOverviewReport::class)->handle($this->admin, $this->week);

        expect($report->created->value)->toBe(4)
            ->and($report->resolved->value)->toBe(2)
            ->and(collect($report->byDesk)->pluck('label')->all())->toBe([$this->desk->name, 'Billing']);
    });
});

test('days and the heatmap follow the viewer\'s timezone at a day boundary', function () {
    $this->agent->update(['timezone' => 'America/New_York']);
    reportTicket('2026-03-11 03:30');

    $tuesday = app(BuildOverviewReport::class)->handle($this->agent, ['from' => '2026-03-10', 'to' => '2026-03-10']);

    expect($tuesday->created->value)->toBe(1)
        ->and($tuesday->volume[0])->date->toBe('2026-03-10')->values->toBe(['created' => 1, 'resolved' => 0])
        ->and($tuesday->heatmap)->toHaveCount(1)
        ->and($tuesday->heatmap[0])->weekday->toBe(2)->hour->toBe(23)->count->toBe(1)
        ->and(app(BuildOverviewReport::class)->handle($this->agent, ['from' => '2026-03-11', 'to' => '2026-03-11'])->created->value)->toBe(0);
});

describe('responses', function () {
    beforeEach(function () {
        foreach ([[30, 30], [90, 60], [2000, 600]] as [$calendar, $business]) {
            reportTicket('2026-03-11 09:00', metric: ['first_responded_at' => '2026-03-12 09:00', 'first_response_minutes' => $calendar, 'first_response_business_minutes' => $business]);
        }
        reportTicket('2026-03-04 09:00', metric: ['first_responded_at' => '2026-03-05 09:00', 'first_response_minutes' => 120, 'first_response_business_minutes' => 120]);

        $ticket = reportTicket('2026-03-11 09:00');
        foreach (['2026-03-12 09:00' => 10, '2026-03-13 09:00' => 20, '2026-03-01 09:00' => 500] as $at => $minutes) {
            TicketComment::factory()->for($ticket)->create(['created_at' => $at, 'response_minutes' => $minutes, 'response_business_minutes' => $minutes]);
        }
    });

    test('reports medians, averages, shares and ranges in calendar minutes', function () {
        $report = app(BuildResponsesReport::class)->handle($this->agent, $this->week);

        expect($report->medianFirstResponse)->toEqual(new KpiData(90.0, 120.0))
            ->and($report->averageFirstResponse->value)->toBe(706.7)
            ->and($report->medianReply->value)->toBe(15.0)
            ->and($report->averageReply->value)->toBe(15.0)
            ->and($report->firstResponseWithinHour)->toEqual(new KpiData(33.3, 0.0))
            ->and($report->firstResponseWithinFourHours->value)->toBe(66.7)
            ->and($report->firstResponseWithinDay->value)->toBe(66.7)
            ->and(collect($report->firstResponseDistribution)->pluck('count', 'key')->all())->toBe(['within_1h' => 1, '1h_4h' => 1, '4h_24h' => 0, '1d_3d' => 1, 'over_3d' => 0])
            ->and($report->trend[2]->values)->toBe(['first_response' => 90.0, 'reply' => 10.0])
            ->and($report->trend[0]->values)->toBe(['first_response' => null, 'reply' => null]);
    });

    test('uses business minutes on the business basis', function () {
        $report = app(BuildResponsesReport::class)->handle($this->agent, [...$this->week, 'basis' => 'business']);

        expect($report->medianFirstResponse->value)->toBe(60.0)
            ->and($report->averageFirstResponse->value)->toBe(230.0)
            ->and($report->firstResponseWithinHour->value)->toBe(66.7);
    });
});

test('resolution reports medians, ranges and the reopen rate', function () {
    foreach ([[100, 0], [300, 1], [20000, 0]] as [$minutes, $reopens]) {
        reportTicket('2026-03-01 09:00', metric: ['resolved_at' => '2026-03-14 09:00', 'resolution_minutes' => $minutes, 'resolution_business_minutes' => $minutes, 'reopen_count' => $reopens]);
    }

    $report = app(BuildResolutionReport::class)->handle($this->agent, $this->week);

    expect($report->medianResolution)->toEqual(new KpiData(300.0, null))
        ->and($report->averageResolution->value)->toBe(6800.0)
        ->and($report->reopenRate->value)->toBe(33.3)
        ->and(collect($report->distribution)->pluck('count', 'key')->all())->toBe(['within_4h' => 1, '4h_24h' => 1, '1d_3d' => 0, '3d_7d' => 0, 'over_7d' => 1])
        ->and($report->trend[4]->values)->toBe(['resolution' => 300.0]);
});

describe('agents', function () {
    beforeEach(function () {
        $this->colleague = deskMember($this->workspace, $this->desk, attributes: ['name' => 'Alex Kim']);
        $this->outsider = deskMember($this->workspace, $this->otherDesk, attributes: ['name' => 'Zoe Park']);

        $solved = reportTicket('2026-03-11 09:00', ['status' => 'solved'], ['first_responded_at' => '2026-03-11 09:10', 'first_responder_id' => $this->agent->id, 'first_response_minutes' => 10, 'first_response_business_minutes' => 10]);
        $solved->assignees()->attach($this->agent->id, ['workspace_id' => $this->workspace->id]);
        reportTicket('2026-03-11 09:00')->assignees()->attach($this->agent->id, ['workspace_id' => $this->workspace->id]);

        $comment = fn (User $author, string $at, array $attributes = []) => TicketComment::factory()->for($solved)->for($author, 'author')->create(['created_at' => $at, ...$attributes]);
        $comment($this->agent, '2026-03-11 09:10', ['response_minutes' => 10, 'response_business_minutes' => 10]);
        $comment($this->agent, '2026-03-12 09:00', ['response_minutes' => 30, 'response_business_minutes' => 30]);
        $comment($this->agent, '2026-03-12 10:00', ['is_internal' => true]);
        $comment($this->agent, '2026-03-01 10:00');
        $comment($this->colleague, '2026-03-12 11:00', ['response_minutes' => 50, 'response_business_minutes' => 50]);

        statusEvent($solved, 'open', 'solved', '2026-03-12 12:00', $this->agent);
        statusEvent($solved, 'solved', 'closed', '2026-03-13 12:00', $this->agent);
        statusEvent(reportTicket('2026-03-11 09:00', ['status' => 'solved']), 'pending', 'solved', '2026-03-12 12:00', $this->colleague);

        $other = reportTicket('2026-03-11 09:00', ['desk_id' => $this->otherDesk->id]);
        TicketComment::factory()->for($other)->for($this->outsider, 'author')->create(['created_at' => '2026-03-12 09:00']);
    });

    test('lists an agent\'s own desk members with their work in the period', function () {
        $rows = collect(app(BuildAgentsReport::class)->handle($this->agent, $this->week)->agents);

        expect($rows->map(fn ($row) => $row->agent->name)->all())->toBe(['Alex Kim', 'Jane Doe'])
            ->and($rows[1])
            ->publicReplies->toBe(2)
            ->internalNotes->toBe(1)
            ->ticketsSolved->toBe(1)
            ->openAssigned->toBe(1)
            ->medianReply->toBe(20.0)
            ->medianFirstResponse->toBe(10.0)
            ->and($rows[0])
            ->publicReplies->toBe(1)
            ->ticketsSolved->toBe(1)
            ->openAssigned->toBe(0)
            ->medianFirstResponse->toBeNull();
    });

    test('lists every workspace member for an admin', function () {
        $rows = collect(app(BuildAgentsReport::class)->handle($this->admin, $this->week)->agents)->keyBy(fn ($row) => $row->agent->id);

        expect($rows)->toHaveCount(4)
            ->and($rows[$this->outsider->id]->publicReplies)->toBe(1);
    });
});

test('sla counts met, breached and in-progress targets of tickets created in the period', function () {
    $target = fn (Ticket $ticket, string $metric, array $attributes = []) => TicketSlaTarget::create(['ticket_id' => $ticket->id, 'metric' => $metric, 'target_minutes' => 60, 'started_at' => $ticket->created_at, ...$attributes]);

    $urgent = reportTicket('2026-03-11 09:00', ['priority' => 'urgent']);
    $target($urgent, 'first_response', ['achieved_at' => '2026-03-11 09:30']);
    $target($urgent, 'resolution', ['breached_at' => '2026-03-12 09:00']);
    $normal = reportTicket('2026-03-11 09:00', ['priority' => 'normal']);
    $target($normal, 'first_response', ['breached_at' => '2026-03-13 09:00', 'achieved_at' => '2026-03-13 10:00']);
    $target($normal, 'resolution');
    $target(reportTicket('2026-03-04 09:00', ['priority' => 'normal']), 'first_response', ['achieved_at' => '2026-03-04 09:30']);

    $report = app(BuildSlaReport::class)->handle($this->agent, $this->week);
    [$firstResponse, $resolution] = $report->metrics;
    $byPriority = collect($firstResponse->byPriority)->keyBy(fn ($row) => $row->priority->value);

    expect($firstResponse)->met->toBe(1)->breached->toBe(1)->inProgress->toBe(0)
        ->and($firstResponse->percentMet)->toEqual(new KpiData(50.0, 100.0))
        ->and($byPriority['urgent'])->met->toBe(1)->percentMet->toBe(100.0)
        ->and($byPriority['normal'])->breached->toBe(1)->percentMet->toBe(0.0)
        ->and($byPriority['low']->percentMet)->toBeNull()
        ->and($resolution)->met->toBe(0)->breached->toBe(1)->inProgress->toBe(1)
        ->and($resolution->percentMet)->toEqual(new KpiData(0.0, null))
        ->and($report->breaches[2]->values)->toBe(['first_response' => 0, 'resolution' => 1])
        ->and($report->breaches[3]->values)->toBe(['first_response' => 1, 'resolution' => 0]);
});

test('satisfaction scores ratings, answered surveys and the latest comments', function () {
    $rated = function (string $rating, string $at, ?string $comment = null, array $ticket = []) {
        $ticket = reportTicket('2026-03-01 09:00', ['survey_sent_at' => '2026-03-11 09:00', ...$ticket]);
        SatisfactionRating::factory()->for($ticket)->create(['rating' => $rating, 'comment' => $comment, 'created_at' => $at]);

        return $ticket;
    };
    $rated('good', '2026-03-12 09:00', 'Great');
    $slow = $rated('bad', '2026-03-13 09:00', 'Slow');
    reportTicket('2026-03-01 09:00', ['survey_sent_at' => '2026-03-11 09:00']);
    $rated('good', '2026-03-12 09:00', ticket: ['desk_id' => $this->otherDesk->id]);
    $rated('good', '2026-03-05 09:00', ticket: ['survey_sent_at' => '2026-03-04 09:00']);

    $report = app(BuildSatisfactionReport::class)->handle($this->agent, $this->week);

    expect($report->good)->toEqual(new KpiData(1, 1))
        ->and($report->bad)->toEqual(new KpiData(1, 0))
        ->and($report->score)->toEqual(new KpiData(50.0, 100.0))
        ->and($report->responseRate)->toEqual(new KpiData(66.7, 100.0))
        ->and($report->trend[2]->values)->toBe(['score' => 100.0])
        ->and($report->trend[3]->values)->toBe(['score' => 0.0])
        ->and($report->byDesk)->toHaveCount(1)
        ->and($report->byDesk[0])->good->toBe(1)->bad->toBe(1)->score->toBe(50.0)
        ->and(collect($report->comments)->pluck('comment')->all())->toBe(['Slow', 'Great'])
        ->and($report->comments[0]->ticketNumber)->toBe($slow->number);
});
