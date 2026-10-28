<?php

namespace App\Actions\Reports;

use App\Data\Reports\BreakdownData;
use App\Data\Reports\KpiData;
use App\Data\Reports\ReportFiltersData;
use App\Data\Reports\ReportPeriodData;
use App\Data\Reports\SeriesPointData;
use App\Enums\ReportBasis;
use App\Models\Desk;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketMetric;
use App\Models\TicketSlaTarget;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

/**
 * What a report covers once its filters are validated: the period and the one before it as UTC
 * instants, the viewer's timezone for bucketing, the basis and the desks. Every query starts
 * from tickets in those desks, so an agent only ever counts the desks they can see.
 */
final readonly class ReportScope
{
    public const int MAX_DAYS = 366;

    public const int DEFAULT_DAYS = 30;

    /** Ranges longer than this are bucketed by week. */
    public const int DAILY_MAX_DAYS = 90;

    /**
     * @param  array<int, string>  $deskIds  The desks counted: the filter's, else every desk the viewer can see.
     * @param  list<string>  $filteredDeskIds  The filter as given, empty for all.
     * @param  bool  $seesEveryAgent  Whether the agents report lists every workspace member, not only the desks' own.
     */
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public CarbonImmutable $previousStart,
        public string $timezone,
        public ReportBasis $basis,
        public array $deskIds,
        public array $filteredDeskIds,
        public bool $seesEveryAgent,
    ) {}

    /**
     * Validate the filters against what the viewer can see and fill in the defaults: the last 30
     * days in the viewer's timezone, calendar basis, every visible desk.
     *
     * @param  array<string, mixed>  $input
     */
    public static function resolve(User $viewer, array $input): self
    {
        $visibleDeskIds = Desk::withTrashed()->visibleTo($viewer)->get()->map(fn (Desk $desk): string => $desk->id)->all();

        $filters = ReportFiltersData::validateAndCreateWith($input, [
            'desk_ids.*' => [Rule::in($visibleDeskIds)],
        ]);

        $timezone = in_array($viewer->timezone, timezone_identifiers_list(), true) ? $viewer->timezone : 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = $filters->from instanceof Optional ? $today->subDays(self::DEFAULT_DAYS - 1) : CarbonImmutable::parse($filters->from, $timezone);
        $to = $filters->to instanceof Optional ? $today : CarbonImmutable::parse($filters->to, $timezone);
        $days = (int) round($from->diffInDays($to)) + 1;

        if ($days > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => __('Reports cover at most :days days.', ['days' => self::MAX_DAYS])]);
        }

        $filteredDeskIds = $filters->deskIds instanceof Optional ? [] : array_values(array_unique($filters->deskIds));

        return new self(
            start: $from->utc(),
            end: $to->addDay()->utc(),
            previousStart: $from->subDays($days)->utc(),
            timezone: $timezone,
            basis: $filters->basis instanceof Optional ? ReportBasis::CALENDAR : $filters->basis,
            deskIds: $filteredDeskIds === [] ? $visibleDeskIds : $filteredDeskIds,
            filteredDeskIds: $filteredDeskIds,
            seesEveryAgent: $filteredDeskIds === [] && $viewer->workspaceRole(Workspace::current())?->isAdmin() === true,
        );
    }

    public function bucket(): string
    {
        return $this->start->diffInDays($this->end) > self::DAILY_MAX_DAYS ? 'week' : 'day';
    }

    public function period(): ReportPeriodData
    {
        $local = fn (CarbonImmutable $instant): string => $instant->setTimezone($this->timezone)->toDateString();

        return new ReportPeriodData(
            from: $local($this->start),
            to: $local($this->end->subDay()),
            previousFrom: $local($this->previousStart),
            previousTo: $local($this->start->subDay()),
            timezone: $this->timezone,
            bucket: $this->bucket(),
            basis: $this->basis,
            deskIds: $this->filteredDeskIds,
        );
    }

    /**
     * Tickets in the scope's desks. Deleted tickets are left out by the soft-delete scope.
     *
     * @return Builder<Ticket>
     */
    public function tickets(): Builder
    {
        return Ticket::query()->whereIn('tickets.desk_id', $this->deskIds);
    }

    /**
     * @return Builder<TicketMetric>
     */
    public function metrics(): Builder
    {
        return $this->joinTickets(TicketMetric::class, 'ticket_metrics');
    }

    /**
     * @return Builder<TicketComment>
     */
    public function comments(): Builder
    {
        return $this->joinTickets(TicketComment::class, 'ticket_comments');
    }

    /**
     * Public agent replies that answered a customer, with their response times.
     *
     * @return Builder<TicketComment>
     */
    public function replies(): Builder
    {
        return $this->comments()->whereNotNull('ticket_comments.response_minutes');
    }

    /**
     * @return Builder<TicketSlaTarget>
     */
    public function slaTargets(): Builder
    {
        return $this->joinTickets(TicketSlaTarget::class, 'ticket_sla_targets');
    }

    /**
     * @return Builder<SatisfactionRating>
     */
    public function ratings(): Builder
    {
        return $this->joinTickets(SatisfactionRating::class, 'satisfaction_ratings');
    }

    /**
     * The agents a report lists: every workspace member for an admin looking at every desk,
     * otherwise the members of the desks in scope.
     *
     * @return Builder<User>
     */
    public function agents(): Builder
    {
        return User::query()->when(
            $this->seesEveryAgent,
            fn (Builder $query) => $query->whereHas('workspaces', fn (Builder $query) => $query->whereKey(Workspace::current()?->getKey())),
            fn (Builder $query) => $query->whereHas('desks', fn (Builder $query) => $query->withTrashed()->whereIn('desks.id', $this->deskIds)),
        );
    }

    /**
     * @return Builder<TicketEvent>
     */
    public function events(): Builder
    {
        return $this->joinTickets(TicketEvent::class, 'ticket_events');
    }

    /**
     * Restrict the query to rows whose `$column` falls in the period, or in the one before it.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function during(Builder $query, string $column, bool $previous = false): Builder
    {
        return $query
            ->where($column, '>=', $previous ? $this->previousStart : $this->start)
            ->where($column, '<', $previous ? $this->start : $this->end);
    }

    /**
     * Measure the period and the one before it with the same closure, which returns its numbers
     * by name, and pair them up.
     *
     * @param  Closure(bool $previous): array<string, int|float|null>  $measure
     * @return array<string, KpiData>
     */
    public function kpis(Closure $measure): array
    {
        $current = $measure(false);
        $previous = $measure(true);

        return collect($current)->map(fn (int|float|null $value, string $key): KpiData => new KpiData($value, $previous[$key]))->all();
    }

    /**
     * The median and average of a minutes column, in minutes to one decimal.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  literal-string  $column
     * @return array{median: float|null, average: float|null}
     */
    public function stats(Builder $query, string $column): array
    {
        $row = $query->toBase()
            ->selectRaw("percentile_cont(0.5) within group (order by {$column}) as median, avg({$column}) as average")
            ->first();

        return ['median' => self::round($row?->median), 'average' => self::round($row?->average)];
    }

    /**
     * Group the query by the day or week of `$column` in the viewer's timezone and read one value per
     * bucket from the `$aggregate` SQL expression.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  literal-string  $column
     * @param  literal-string  $aggregate
     * @return Collection<string, int|float|null>
     */
    public function perBucket(Builder $query, string $column, string $aggregate): Collection
    {
        return $query->toBase()
            ->selectRaw("to_char(date_trunc(?, {$column} at time zone ?), 'YYYY-MM-DD') as bucket, {$aggregate} as value", [$this->bucket(), $this->timezone])
            ->groupBy('bucket')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->bucket => is_int($row->value) ? $row->value : self::round($row->value)]);
    }

    /**
     * Lay named per-bucket values over every bucket of the period, so empty days still show.
     *
     * @param  array<string, Collection<string, int|float|null>>  $series
     * @return array<int, SeriesPointData>
     */
    public function series(array $series, int|float|null $empty = null): array
    {
        $bucket = $this->bucket();
        $date = $this->start->setTimezone($this->timezone);
        $date = $bucket === 'week' ? $date->startOfWeek() : $date;
        $end = $this->end->setTimezone($this->timezone);
        $points = [];

        while ($date < $end) {
            $key = $date->toDateString();
            $points[] = new SeriesPointData($key, array_map(fn (Collection $values): int|float|null => $values[$key] ?? $empty, $series));
            $date = $bucket === 'week' ? $date->addWeek() : $date->addDay();
        }

        return $points;
    }

    /**
     * Count a minutes column into ranges, given as `key => [upper bound in minutes or null, label]`
     * in ascending order, the last one open-ended. Every range is listed, empty ones with zero.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  literal-string  $column
     * @param  array<string, array{int|null, string}>  $ranges
     * @return array<int, BreakdownData>
     */
    public function distribution(Builder $query, string $column, array $ranges): array
    {
        // width_bucket() numbers each value by how many thresholds it reached; minutes are whole,
        // so "up to 60" is "below 61".
        $thresholds = collect($ranges)->pluck(0)->filter()->map(fn (int $bound): int => $bound + 1);

        $counts = $query->toBase()
            ->selectRaw("width_bucket({$column}, ?::int[]) as range_index, count(*) as count", ['{'.$thresholds->implode(',').'}'])
            ->groupBy('range_index')
            ->pluck('count', 'range_index');

        return collect($ranges)
            ->values()
            ->map(fn (array $range, int $index): BreakdownData => new BreakdownData(array_keys($ranges)[$index], $range[1], (int) ($counts[$index] ?? 0)))
            ->all();
    }

    public static function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 1);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    protected function joinTickets(string $model, string $table): Builder
    {
        return $model::query()
            ->join('tickets', 'tickets.id', '=', "{$table}.ticket_id")
            ->whereNull('tickets.deleted_at')
            ->whereIn('tickets.desk_id', $this->deskIds);
    }
}
