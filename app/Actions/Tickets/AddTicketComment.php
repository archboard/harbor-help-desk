<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AddTicketComment
{
    /**
     * Reply on the ticket, or add an internal note when `is_internal` is true.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Ticket $ticket, array $input): TicketComment
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $validated = Validator::make($input, [
            'body' => ['required', 'string', 'max:65535'],
            'is_internal' => ['sometimes', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $ticket, $validated): TicketComment {
            $comment = new TicketComment([
                'body' => $validated['body'],
                'is_internal' => $validated['is_internal'] ?? false,
            ]);
            $comment->author()->associate($actor);
            $ticket->comments()->save($comment);

            $ticket->recordEvent(
                $comment->is_internal ? TicketEventType::INTERNAL_NOTE_ADDED : TicketEventType::COMMENT_ADDED,
                $actor,
                ['comment_id' => $comment->id],
            );

            return $comment;
        });
    }
}
