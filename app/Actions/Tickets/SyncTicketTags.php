<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SyncTicketTags
{
    /**
     * Make `names` the ticket's tags, matching existing tags ignoring case and creating the
     * rest, and record each tag added or removed.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Ticket $ticket, array $input): void
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $names = Validator::make($input, [
            'names' => ['present', 'array'],
            'names.*' => ['required', 'string', 'max:255'],
        ])->safe()->collect('names')
            ->map(fn (string $name): string => trim($name))
            ->unique(fn (string $name): string => mb_strtolower($name));

        DB::transaction(function () use ($actor, $ticket, $names): void {
            $tags = $this->findOrCreate($names);
            $current = $ticket->tags()->get();

            foreach ($tags->diff($current) as $tag) {
                $ticket->tags()->attach($tag, ['workspace_id' => $ticket->workspace_id]);
                $ticket->recordEvent(TicketEventType::TAG_ADDED, $actor, ['tag_id' => $tag->id, 'name' => $tag->name]);
            }

            foreach ($current->diff($tags) as $tag) {
                $ticket->tags()->detach($tag);
                $ticket->recordEvent(TicketEventType::TAG_REMOVED, $actor, ['tag_id' => $tag->id, 'name' => $tag->name]);
            }
        });

        $ticket->unsetRelation('tags');
    }

    /**
     * @param  Collection<int, string>  $names
     * @return EloquentCollection<int, Tag>
     */
    protected function findOrCreate(Collection $names): EloquentCollection
    {
        $existing = Tag::query()
            ->whereIn(DB::raw('lower(name)'), $names->map(fn (string $name): string => mb_strtolower($name)))
            ->get();

        $missing = $names->reject(fn (string $name): bool => $existing->contains(
            fn (Tag $tag): bool => mb_strtolower($tag->name) === mb_strtolower($name),
        ));

        return $existing->merge($missing->map(fn (string $name): Tag => Tag::create(['name' => $name])));
    }
}
