<?php

namespace App\Mail;

use App\Enums\MailboxDriver;
use App\Mail\Inbound\InboundEmail;
use App\Models\Attachment as TicketAttachment;
use App\Models\Mailbox;
use App\Models\TicketComment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * An agent's reply emailed to the requester, threaded onto the conversation by its headers.
 */
class TicketReplyMail extends Mailable
{
    /**
     * @param  string  $messageId  without angle brackets
     * @param  array<int, string>  $references  earlier Message-IDs on the ticket, oldest first
     */
    public function __construct(
        public TicketComment $comment,
        public Mailbox $mailbox,
        public string $messageId,
        public array $references = [],
    ) {}

    public function envelope(): Envelope
    {
        $ticket = $this->comment->ticket;
        $subject = preg_replace('/^(?:(?:re|fwd?):\s*)+/i', '', $ticket->subject);

        return new Envelope(
            from: $this->mailbox->fromAddress(),
            subject: "Re: {$subject} [#{$ticket->number}]",
            // Postmark echoes these on bounce webhooks; other servers would pass them to the customer.
            metadata: $this->mailbox->driver === MailboxDriver::HOSTED
                ? ['workspace_id' => $this->comment->workspace_id, 'comment_id' => $this->comment->id]
                : [],
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            messageId: $this->messageId,
            references: $this->references,
            text: array_filter([
                'In-Reply-To' => $this->references === [] ? null : '<'.end($this->references).'>',
                'X-Auto-Response-Suppress' => 'OOF, AutoReply',
                'Auto-Submitted' => 'no',
            ]),
        );
    }

    public function content(): Content
    {
        $workspace = $this->comment->workspace;

        return new Content(
            view: 'mail.ticket-reply',
            text: 'mail.ticket-reply-text',
            with: [
                'marker' => InboundEmail::REPLY_MARKER,
                'ticket' => $this->comment->ticket,
                'workspaceName' => $workspace->name,
                'portalUrl' => $workspace->settings->portalEnabled ? $workspace->url("/portal/tickets/{$this->comment->ticket_id}") : null,
            ],
        );
    }

    /**
     * The comment's files, sent along like the agent would expect from any mail client.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->comment->attachments
            ->map(fn (TicketAttachment $file): Attachment => Attachment::fromStorageDisk($file->disk, $file->path)->as($file->filename)->withMime($file->mime_type))
            ->all();
    }
}
