<?php

namespace App\Actions\Email;

use App\Enums\TicketEventType;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use Illuminate\Support\Str;

class RecordEmailBounce
{
    /**
     * Log on the ticket that the emailed copy of a reply couldn't be delivered.
     */
    public function handle(TicketComment $comment, string $reason): TicketEvent
    {
        return $comment->ticket->recordEvent(TicketEventType::EMAIL_BOUNCED, null, [
            'comment_id' => $comment->id,
            'reason' => Str::limit(Str::squish($reason), 300) ?: __('No reason given.'),
        ]);
    }
}
