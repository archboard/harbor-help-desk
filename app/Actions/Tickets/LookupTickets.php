<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\LookupTicketsData;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class LookupTickets
{
    public const int LIMIT = 10;

    /**
     * A few of the tickets the actor can see, newest first, for a picker: `123` or `#123` matches
     * the number, any other text matches part of the subject. Nothing is returned without a search.
     *
     * @param  array<string, mixed>  $input
     * @return Collection<int, Ticket>
     */
    public function handle(User $actor, array $input): Collection
    {
        Gate::forUser($actor)->authorize('viewAny', Ticket::class);

        $search = LookupTicketsData::validateAndCreate($input)->search;
        $search = $search instanceof Optional ? '' : trim((string) $search);

        if ($search === '') {
            return new Collection;
        }

        $query = Ticket::visibleTo($actor);

        if (preg_match('/^#?(\d+)$/', $search, $matches)) {
            $query->where('number', (int) $matches[1]);
        } else {
            $query->whereLike('subject', '%'.addcslashes($search, '%_\\').'%');
        }

        return $query->latest('updated_at')->orderByDesc('id')->limit(self::LIMIT)->get();
    }
}
