<?php

namespace App\Actions\Reports;

use App\Data\Reports\ResponsesReportData;
use App\Models\User;

class BuildResponsesReport
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, array $input): ResponsesReportData
    {
        $scope = ReportScope::resolve($viewer, $input);
        $firstResponse = $scope->basis->firstResponseColumn();
        $reply = $scope->basis->replyColumn();

        $kpis = $scope->kpis(function (bool $previous) use ($scope, $firstResponse, $reply): array {
            $firstResponses = $scope->during($scope->metrics(), 'ticket_metrics.first_responded_at', $previous);
            $first = $scope->stats(clone $firstResponses, $firstResponse);
            $replies = $scope->stats($scope->during($scope->replies(), 'ticket_comments.created_at', $previous), $reply);
            $within = $firstResponses->toBase()
                ->selectRaw("avg(({$firstResponse} <= 60)::int) * 100 as h1, avg(({$firstResponse} <= 240)::int) * 100 as h4, avg(({$firstResponse} <= 1440)::int) * 100 as h24")
                ->first();

            return [
                'medianFirstResponse' => $first['median'],
                'averageFirstResponse' => $first['average'],
                'medianReply' => $replies['median'],
                'averageReply' => $replies['average'],
                'within1h' => ReportScope::round($within?->h1),
                'within4h' => ReportScope::round($within?->h4),
                'within24h' => ReportScope::round($within?->h24),
            ];
        });

        $firstResponses = $scope->during($scope->metrics(), 'ticket_metrics.first_responded_at');

        return new ResponsesReportData(
            period: $scope->period(),
            medianFirstResponse: $kpis['medianFirstResponse'],
            averageFirstResponse: $kpis['averageFirstResponse'],
            medianReply: $kpis['medianReply'],
            averageReply: $kpis['averageReply'],
            firstResponseWithinHour: $kpis['within1h'],
            firstResponseWithinFourHours: $kpis['within4h'],
            firstResponseWithinDay: $kpis['within24h'],
            firstResponseDistribution: $scope->distribution(clone $firstResponses, $firstResponse, [
                'within_1h' => [60, __('Within 1 hour')],
                '1h_4h' => [240, __('1–4 hours')],
                '4h_24h' => [1440, __('4–24 hours')],
                '1d_3d' => [4320, __('1–3 days')],
                'over_3d' => [null, __('Over 3 days')],
            ]),
            trend: $scope->series([
                'first_response' => $scope->perBucket($firstResponses, 'ticket_metrics.first_responded_at', "percentile_cont(0.5) within group (order by {$firstResponse})"),
                'reply' => $scope->perBucket($scope->during($scope->replies(), 'ticket_comments.created_at'), 'ticket_comments.created_at', "percentile_cont(0.5) within group (order by {$reply})"),
            ]),
        );
    }
}
