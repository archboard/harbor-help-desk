<?php

namespace App\Actions\Reports;

use App\Data\Reports\SatisfactionCommentData;
use App\Data\Reports\SatisfactionDeskData;
use App\Data\Reports\SatisfactionReportData;
use App\Enums\SatisfactionScore;
use App\Models\Desk;
use App\Models\SatisfactionRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BuildSatisfactionReport
{
    public const int COMMENTS = 20;

    /**
     * Good and bad ratings given in the period, the score over time and by desk, how many surveys
     * got answered, and the latest comments.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $viewer, array $input): SatisfactionReportData
    {
        $scope = ReportScope::resolve($viewer, $input);
        $good = SatisfactionScore::GOOD->value;

        $kpis = $scope->kpis(function (bool $previous) use ($scope, $good): array {
            $counts = $scope->during($scope->ratings(), 'satisfaction_ratings.created_at', $previous)
                ->toBase()
                ->selectRaw("count(*) filter (where satisfaction_ratings.rating = '{$good}') as good, count(*) filter (where satisfaction_ratings.rating <> '{$good}') as bad")
                ->first();
            $surveyed = $scope->during($scope->tickets(), 'tickets.survey_sent_at', $previous);
            $sent = (clone $surveyed)->count();
            $answered = $surveyed->whereHas('satisfactionRating')->count();

            return [
                'good' => (int) ($counts->good ?? 0),
                'bad' => (int) ($counts->bad ?? 0),
                'score' => $this->score((int) ($counts->good ?? 0), (int) ($counts->bad ?? 0)),
                'responseRate' => $sent === 0 ? null : ReportScope::round($answered / $sent * 100),
            ];
        });

        $ratings = $scope->during($scope->ratings(), 'satisfaction_ratings.created_at');

        return new SatisfactionReportData(
            period: $scope->period(),
            good: $kpis['good'],
            bad: $kpis['bad'],
            score: $kpis['score'],
            responseRate: $kpis['responseRate'],
            trend: $scope->series([
                'score' => $scope->perBucket(clone $ratings, 'satisfaction_ratings.created_at', "avg((satisfaction_ratings.rating = '{$good}')::int) * 100"),
            ]),
            byDesk: $this->byDesk(clone $ratings),
            comments: $ratings
                ->whereNotNull('satisfaction_ratings.comment')
                ->select('satisfaction_ratings.*', 'tickets.number as ticket_number')
                ->latest('satisfaction_ratings.created_at')->latest('satisfaction_ratings.id')
                ->limit(self::COMMENTS)
                ->get()
                ->map(fn (SatisfactionRating $rating): SatisfactionCommentData => new SatisfactionCommentData(
                    ticketId: $rating->ticket_id,
                    ticketNumber: (int) $rating->getAttribute('ticket_number'),
                    rating: $rating->rating,
                    comment: (string) $rating->comment,
                    createdAt: $rating->created_at,
                ))
                ->all(),
        );
    }

    /**
     * @param  Builder<SatisfactionRating>  $ratings
     * @return array<int, SatisfactionDeskData>
     */
    protected function byDesk(Builder $ratings): array
    {
        $good = SatisfactionScore::GOOD->value;
        $rows = $ratings->toBase()
            ->selectRaw("tickets.desk_id, count(*) filter (where satisfaction_ratings.rating = '{$good}') as good, count(*) filter (where satisfaction_ratings.rating <> '{$good}') as bad")
            ->groupBy('tickets.desk_id')
            ->get();
        $names = Desk::withTrashed()->whereIn('id', $rows->pluck('desk_id'))->pluck('name', 'id');

        return $rows
            ->map(fn (object $row): SatisfactionDeskData => new SatisfactionDeskData($row->desk_id, $names[$row->desk_id], $row->good, $row->bad, (float) $this->score($row->good, $row->bad)))
            ->sortBy('name')
            ->values()
            ->all();
    }

    protected function score(int $good, int $bad): ?float
    {
        return $good + $bad === 0 ? null : ReportScope::round($good / ($good + $bad) * 100);
    }
}
