<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\SearchTicketsData;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SearchTickets
{
    /**
     * The tickets the actor can see, narrowed by any of:
     *
     * - `search`: `#123` or `123` matches the ticket number; other text matches the subject
     *   and comments (Postgres web-search syntax) or the requester's name or email.
     * - `desk_id`, `status`, `priority`, `tag` (a tag name): one value or a list.
     * - `assignee_id`: a user ID, `me` or `none`.
     * - `requester_id`: a contact ID.
     * - `deleted`: only deleted tickets.
     * - `sort`: `updated` (default, newest first), `created`, `due` (soonest first) or
     *   `priority` (most urgent first).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public function handle(User $actor, array $filters): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Ticket::class);

        $validated = SearchTicketsData::validateAndCreate($filters)->filled();

        foreach (SearchTicketsData::LIST_FILTERS as $key) {
            if (isset($validated[$key])) {
                $validated[$key] = Arr::wrap($validated[$key]);
            }
        }

        $search = trim((string) ($validated['search'] ?? ''));

        $query = Ticket::visibleTo($actor)
            ->when($search !== '', fn (Builder $query) => $this->search($query, $search))
            ->when($validated['desk_id'] ?? null, fn (Builder $query, array $deskIds) => $query->whereIn('desk_id', $deskIds))
            ->when($validated['status'] ?? null, fn (Builder $query, array $statuses) => $query->whereIn('status', $statuses))
            ->when($validated['priority'] ?? null, fn (Builder $query, array $priorities) => $query->whereIn('priority', $priorities))
            ->when($validated['tag'] ?? null, fn (Builder $query, array $tags) => $query->whereHas('tags', fn (Builder $query) => $query->whereIn(DB::raw('lower(name)'), array_map(mb_strtolower(...), $tags))))
            ->when($validated['assignee_id'] ?? null, fn (Builder $query, string $assignee) => match ($assignee) {
                'none' => $query->whereDoesntHave('assignees'),
                default => $query->whereHas('assignees', fn (Builder $query) => $query->whereKey($assignee === 'me' ? $actor->getKey() : $assignee)),
            })
            ->when($validated['requester_id'] ?? null, fn (Builder $query, string $contactId) => $query->where('requester_id', $contactId))
            ->when((bool) ($validated['deleted'] ?? false), fn (Builder $query) => $query->onlyTrashed());

        return $this->sort($query, $validated['sort'] ?? 'updated');
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    private function search(Builder $query, string $search): void
    {
        if (preg_match('/^#?(\d+)$/', $search, $matches)) {
            $query->where('number', (int) $matches[1]);

            return;
        }

        $like = '%'.addcslashes($search, '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->whereFullText('subject', $search, ['mode' => 'websearch'])
            ->orWhereHas('comments', fn (Builder $query) => $query->whereFullText('body', $search, ['mode' => 'websearch']))
            ->orWhereHas('requester', fn (Builder $query) => $query
                ->whereLike('name', $like)
                ->orWhereLike('email', $like)));
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function sort(Builder $query, string $sort): Builder
    {
        $mostUrgentFirst = array_reverse(array_column(TicketPriority::cases(), 'value'));

        $query = match ($sort) {
            'created' => $query->latest(),
            'due' => $query->orderByRaw('due_at asc nulls last'),
            'priority' => $query
                ->orderByRaw('array_position(array['.implode(',', array_fill(0, count($mostUrgentFirst), '?')).']::text[], priority)', $mostUrgentFirst)
                ->latest('updated_at'),
            default => $query->latest('updated_at'),
        };

        return $query->orderByDesc('id');
    }
}
