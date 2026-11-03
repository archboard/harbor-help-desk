<?php

namespace App\Actions\Audit;

use App\Data\Audit\ListAuditEventsData;
use App\Models\AuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class ListAuditEvents
{
    public const int PER_PAGE = 50;

    /**
     * The workspace's admin activity, newest first, filtered by action, actor and date. Pass the
     * `cursor` query parameter to read the next page.
     *
     * @param  array<string, mixed>  $input
     * @return CursorPaginator<int, AuditEvent>
     */
    public function handle(User $actor, array $input): CursorPaginator
    {
        Gate::forUser($actor)->authorize('viewAny', AuditEvent::class);

        $filters = ListAuditEventsData::validateAndCreate($input);

        $from = $filters->from instanceof Optional ? null : CarbonImmutable::parse($filters->from);
        $to = $filters->to instanceof Optional ? null : CarbonImmutable::parse($filters->to)->endOfDay();

        return AuditEvent::query()
            ->with('actor')
            ->when(! $filters->action instanceof Optional, fn ($query) => $query->where('action', $filters->action))
            ->when(! $filters->actorId instanceof Optional, fn ($query) => $query->where('actor_id', $filters->actorId))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            // ponytail: ids are time-ordered, and the cursor reads `id` off the Data objects, which have no `created_at`.
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }
}
