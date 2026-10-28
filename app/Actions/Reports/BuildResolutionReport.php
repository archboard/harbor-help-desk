<?php

namespace App\Actions\Reports;

use App\Data\Reports\ResolutionReportData;
use App\Models\User;

class BuildResolutionReport
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, array $input): ResolutionReportData
    {
        $scope = ReportScope::resolve($viewer, $input);
        $resolution = $scope->basis->resolutionColumn();

        $kpis = $scope->kpis(function (bool $previous) use ($scope, $resolution): array {
            $resolved = $scope->during($scope->metrics(), 'ticket_metrics.resolved_at', $previous);
            $stats = $scope->stats(clone $resolved, $resolution);

            return [
                'median' => $stats['median'],
                'average' => $stats['average'],
                'reopenRate' => ReportScope::round($resolved->toBase()->selectRaw('avg((ticket_metrics.reopen_count > 0)::int) * 100 as rate')->value('rate')),
            ];
        });

        $resolved = $scope->during($scope->metrics(), 'ticket_metrics.resolved_at');

        return new ResolutionReportData(
            period: $scope->period(),
            medianResolution: $kpis['median'],
            averageResolution: $kpis['average'],
            reopenRate: $kpis['reopenRate'],
            distribution: $scope->distribution(clone $resolved, $resolution, [
                'within_4h' => [240, __('Within 4 hours')],
                '4h_24h' => [1440, __('4–24 hours')],
                '1d_3d' => [4320, __('1–3 days')],
                '3d_7d' => [10080, __('3–7 days')],
                'over_7d' => [null, __('Over 7 days')],
            ]),
            trend: $scope->series([
                'resolution' => $scope->perBucket($resolved, 'ticket_metrics.resolved_at', "percentile_cont(0.5) within group (order by {$resolution})"),
            ]),
        );
    }
}
