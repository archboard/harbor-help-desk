<?php

namespace App\Actions\Reports;

use App\Data\Reports\AgentRowData;
use App\Data\Reports\AgentsReportData;
use App\Data\Users\UserData;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\User;

class BuildAgentsReport
{
    /**
     * Each agent's replies, notes, solves, open assignments and response medians on the tickets in
     * scope. Agents only see the members of their own desks.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, array $input): AgentsReportData
    {
        $scope = ReportScope::resolve($viewer, $input);
        $userType = (new User)->getMorphClass();
        $reply = $scope->basis->replyColumn();
        $firstResponse = $scope->basis->firstResponseColumn();
        $resolved = TicketStatus::resolvedValues();

        $comments = $scope->during($scope->comments(), 'ticket_comments.created_at')
            ->where('ticket_comments.author_type', $userType)
            ->toBase()
            ->selectRaw('ticket_comments.author_id, count(*) filter (where not ticket_comments.is_internal) as public_replies, count(*) filter (where ticket_comments.is_internal) as internal_notes')
            ->groupBy('ticket_comments.author_id')
            ->get()
            ->keyBy('author_id');

        $medianReplies = $scope->during($scope->replies(), 'ticket_comments.created_at')
            ->where('ticket_comments.author_type', $userType)
            ->toBase()
            ->selectRaw("ticket_comments.author_id, percentile_cont(0.5) within group (order by {$reply}) as median")
            ->groupBy('ticket_comments.author_id')
            ->pluck('median', 'author_id');

        $medianFirstResponses = $scope->during($scope->metrics(), 'ticket_metrics.first_responded_at')
            ->whereNotNull('ticket_metrics.first_responder_id')
            ->toBase()
            ->selectRaw("ticket_metrics.first_responder_id, percentile_cont(0.5) within group (order by {$firstResponse}) as median")
            ->groupBy('ticket_metrics.first_responder_id')
            ->pluck('median', 'first_responder_id');

        $solved = $scope->during($scope->events(), 'ticket_events.created_at')
            ->where('ticket_events.type', TicketEventType::STATUS_CHANGED)
            ->where('ticket_events.actor_type', $userType)
            ->whereIn('ticket_events.properties->to', $resolved)
            ->whereNotIn('ticket_events.properties->from', $resolved)
            ->toBase()
            ->selectRaw('ticket_events.actor_id, count(distinct ticket_events.ticket_id) as count')
            ->groupBy('ticket_events.actor_id')
            ->pluck('count', 'actor_id');

        $openAssigned = $scope->tickets()
            ->join('ticket_user', 'ticket_user.ticket_id', '=', 'tickets.id')
            ->whereNotIn('tickets.status', $resolved)
            ->toBase()
            ->selectRaw('ticket_user.user_id, count(*) as count')
            ->groupBy('ticket_user.user_id')
            ->pluck('count', 'user_id');

        return new AgentsReportData(
            period: $scope->period(),
            agents: $scope->agents()->orderBy('name')->get()->map(fn (User $agent): AgentRowData => new AgentRowData(
                agent: UserData::from($agent),
                publicReplies: (int) ($comments[$agent->id]->public_replies ?? 0),
                internalNotes: (int) ($comments[$agent->id]->internal_notes ?? 0),
                ticketsSolved: (int) ($solved[$agent->id] ?? 0),
                openAssigned: (int) ($openAssigned[$agent->id] ?? 0),
                medianReply: ReportScope::round($medianReplies[$agent->id] ?? null),
                medianFirstResponse: ReportScope::round($medianFirstResponses[$agent->id] ?? null),
            ))->all(),
        );
    }
}
