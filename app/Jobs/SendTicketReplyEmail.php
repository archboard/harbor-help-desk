<?php

namespace App\Jobs;

use App\Mail\TicketReplyMail;
use App\Models\Mailbox;
use App\Models\TicketComment;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Email an agent's public reply to the ticket's requester through the ticket's mailbox. With no
 * mailbox in the workspace, nothing is sent.
 */
class SendTicketReplyEmail implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * A reply deleted before the job ran isn't sent.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public TicketComment $comment) {}

    public function handle(): void
    {
        $comment = $this->comment;
        $ticket = $comment->ticket;
        $mailbox = Mailbox::forTicket($ticket);

        if ($mailbox === null) {
            return;
        }

        $references = $ticket->comments()
            ->whereNotNull('message_id')
            ->whereKeyNot($comment->getKey())
            ->reorder()
            ->latest('id')
            ->limit(10)
            ->pluck('message_id')
            ->reverse()
            ->values()
            ->all();

        $comment->message_id ??= "{$comment->id}@".config('harbor.domain');
        $comment->save();

        $mailbox->mailer()
            ->to($ticket->requester)
            ->send(new TicketReplyMail($comment, $mailbox, $comment->message_id, $references));
    }
}
