<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SearchTickets
{
    /**
     * The tickets the actor can see, filtered by any of `desk_id`, `status`, `priority`,
     * `assignee_id` and `tag` (a tag name), newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public function handle(User $actor, array $filters): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Ticket::class);

        $validated = Validator::make($filters, [
            'desk_id' => ['sometimes', 'string'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'assignee_id' => ['sometimes', 'string'],
            'tag' => ['sometimes', 'string'],
        ])->validate();

        return Ticket::visibleTo($actor)
            ->when($validated['desk_id'] ?? null, fn (Builder $query, string $deskId) => $query->where('desk_id', $deskId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($validated['assignee_id'] ?? null, fn (Builder $query, string $userId) => $query->whereHas('assignees', fn (Builder $query) => $query->whereKey($userId)))
            ->when($validated['tag'] ?? null, fn (Builder $query, string $tag) => $query->whereHas('tags', fn (Builder $query) => $query->whereRaw('lower(name) = lower(?)', [$tag])))
            ->latest()
            ->orderByDesc('id');
    }
}
