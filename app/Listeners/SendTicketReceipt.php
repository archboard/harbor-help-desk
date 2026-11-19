<?php

namespace App\Listeners;

use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;
use App\Mail\TicketReceiptMail;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\TicketComment;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Email the requester a receipt when they open a ticket themselves, by email or the portal, on a
 * desk that sends receipts. Queued, and tenant-aware by the multitenancy default. Auto-replies
 * are dropped on the way in, so a receipt never answers another robot.
 */
class SendTicketReceipt implements ShouldQueue
{
    public function handle(TicketActivityRecorded $event): void
    {
        $ticketEvent = $event->ticketEvent;
        $ticket = $ticketEvent->ticket;

        if ($ticketEvent->type !== TicketEventType::CREATED
            || ! $ticketEvent->actor instanceof Contact
            || $ticket === null
            || ! $ticket->desk->sends_receipts) {
            return;
        }

        $mailbox = Mailbox::forTicket($ticket);

        if ($mailbox === null) {
            return;
        }

        $openingMessageId = TicketComment::query()->whereKey($ticketEvent->properties['comment_id'] ?? null)->value('message_id');

        $mailbox->mailer()
            ->to($ticket->requester)
            ->send(new TicketReceiptMail($ticket, $mailbox, $openingMessageId));
    }
}
