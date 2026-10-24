<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\RateTicketData;
use App\Enums\TicketEventType;
use App\Models\Contact;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RateTicket
{
    /**
     * Record the requester's `rating` (good or bad) and optional `comment` on their resolved ticket. A ticket has one
     * rating, so rating again replaces it. Every rating is recorded as a ticket event.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function handle(Contact $contact, Ticket $ticket, array $input): SatisfactionRating
    {
        $data = RateTicketData::validateAndCreate($input);

        if ($ticket->requester_id !== $contact->getKey()) {
            throw ValidationException::withMessages(['rating' => __('Only the requester can rate this ticket.')]);
        }

        if (! $ticket->status->isResolved()) {
            throw ValidationException::withMessages(['rating' => __('This ticket can be rated once it is solved.')]);
        }

        return DB::transaction(function () use ($contact, $ticket, $data): SatisfactionRating {
            $rating = SatisfactionRating::query()->updateOrCreate(
                ['ticket_id' => $ticket->getKey()],
                ['contact_id' => $contact->getKey(), 'rating' => $data->rating, 'comment' => $data->filled()['comment'] ?? null],
            );

            $ticket->recordEvent(TicketEventType::SATISFACTION_RATED, $contact, ['rating' => $data->rating->value]);

            return $rating;
        });
    }
}
