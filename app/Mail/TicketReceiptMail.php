<?php

namespace App\Mail;

use App\Mail\Inbound\InboundEmail;
use App\Models\Mailbox;
use App\Models\Ticket;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Tells the requester their new ticket was received. Threaded onto their opening email, and
 * carrying `[#number]` in the subject, so answering it adds to the ticket.
 */
class TicketReceiptMail extends Mailable
{
    /**
     * @param  string|null  $inReplyTo  the opening email's Message-ID, without angle brackets
     */
    public function __construct(
        public Ticket $ticket,
        public Mailbox $mailbox,
        public ?string $inReplyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = preg_replace('/^(?:(?:re|fwd?):\s*)+/i', '', $this->ticket->subject);

        return new Envelope(
            from: $this->mailbox->fromAddress(),
            subject: "Re: {$subject} [#{$this->ticket->number}]",
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            references: array_filter([$this->inReplyTo]),
            text: array_filter([
                'In-Reply-To' => $this->inReplyTo === null ? null : "<{$this->inReplyTo}>",
                'Auto-Submitted' => 'auto-replied',
                'X-Auto-Response-Suppress' => 'All',
            ]),
        );
    }

    public function content(): Content
    {
        $workspace = $this->ticket->workspace;

        return new Content(
            markdown: 'mail.ticket-receipt',
            with: [
                'marker' => InboundEmail::REPLY_MARKER,
                'workspaceName' => $workspace->name,
                'portalUrl' => $workspace->settings->portalEnabled ? $workspace->url("/portal/tickets/{$this->ticket->id}") : null,
            ],
        );
    }
}
