<?php

namespace App\Mail;

use App\Models\Mailbox;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A short email proving a mailbox can send, in the branded layout notifications use.
 */
class MailboxTestMail extends Mailable
{
    public function __construct(public Mailbox $mailbox) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: $this->mailbox->fromAddress(), subject: __('Harbor test email'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.mailbox-test');
    }
}
