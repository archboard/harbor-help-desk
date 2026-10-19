<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SearchContacts
{
    /**
     * The workspace's contacts by name, matching `search` against name or email, each with a
     * count of the tickets the actor can see.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Contact>
     */
    public function handle(User $actor, array $filters): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Contact::class);

        $search = trim((string) (Validator::make($filters, [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate()['search'] ?? ''));

        $like = '%'.addcslashes($search, '%_\\').'%';

        return Contact::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', $like)
                ->orWhereLike('email', $like)))
            // Only tickets on desks the actor can see, as Ticket::visibleTo() does.
            ->withCount(['tickets' => fn (Builder $query) => $query->whereIn('desk_id', Desk::visibleTo($actor)->select('id'))])
            ->orderBy('name')
            ->orderBy('id');
    }
}
