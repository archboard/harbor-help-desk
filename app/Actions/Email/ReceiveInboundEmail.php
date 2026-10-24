<?php

namespace App\Actions\Email;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\CreateTicket;
use App\Enums\TicketSource;
use App\Mail\Inbound\InboundEmail;
use App\Models\Contact;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * ponytail: outcomes go to the log, not an inbound message table; add one if support needs it.
 */
class ReceiveInboundEmail
{
    public function __construct(
        protected CreateTicket $createTicket,
        protected AddTicketComment $addTicketComment,
        protected RecordEmailBounce $recordEmailBounce,
    ) {}

    /**
     * Turn mail received by the mailbox into a reply on the ticket it answers, or a new ticket on
     * the desk its routing picks. Loops, auto-replies, duplicates and senders over the hourly
     * limit are dropped, and bounces are logged on the ticket. The mailbox's workspace must be
     * current.
     */
    public function handle(Mailbox $mailbox, InboundEmail $email): ?TicketComment
    {
        $context = ['mailbox_id' => $mailbox->id, 'message_id' => $email->messageId, 'from' => $email->fromEmail];

        $outcome = match (true) {
            $email->fromEmail === '' => 'no sender',
            $this->isLoop($email) => 'loop',
            $email->isBounce() => $this->recordBounce($email),
            $email->isAutoReply() => 'auto-reply',
            TicketComment::withTrashed()->where('message_id', $email->messageId)->exists() => 'duplicate',
            default => null,
        };

        if ($outcome !== null) {
            Log::info("Inbound email dropped: {$outcome}", $context);

            return null;
        }

        $ticket = $this->threadedComment($email)->ticket ?? $this->ticketBySubject($email);

        if ($ticket === null && $this->isOverRateLimit($mailbox, $email)) {
            Log::info('Inbound email dropped: rate limit', $context);

            return null;
        }

        // A new ticket's author is always a contact, who becomes its requester.
        $author = $this->agent($email, $ticket)
            ?? Contact::findOrCreateByEmail($email->fromEmail, $email->fromName ?? Str::before($email->fromEmail, '@'));
        [$attachments, $skipped] = $this->attachments($email);
        $body = Str::limit(implode("\n\n", array_filter([$email->replyText(), ...$skipped])) ?: __('(empty message)'), 65000);
        $input = ['body' => $body, 'attachments' => $attachments, 'message_id' => $email->messageId];

        try {
            $comment = $ticket === null
                ? $this->createTicket->handle($author, $mailbox->deskFor($email), [
                    ...$input,
                    'subject' => Str::limit($email->subject, 250) ?: __('(no subject)'),
                    'requester_id' => $author->id,
                    'mailbox_id' => $mailbox->id,
                ], TicketSource::EMAIL)->comments()->sole()
                : $this->reply($author, $ticket, $input);
        } catch (UniqueConstraintViolationException) {
            Log::info('Inbound email dropped: duplicate', $context);

            return null;
        } finally {
            foreach ($attachments as $file) {
                @unlink($file->getPathname());
            }
        }

        Log::info($ticket === null ? 'Inbound email opened a ticket' : 'Inbound email added a reply', [...$context, 'comment_id' => $comment->id]);

        return $comment;
    }

    /**
     * Mail sent by one of the workspace's own mailboxes, e.g. one forwarding to another.
     */
    protected function isLoop(InboundEmail $email): bool
    {
        return Mailbox::query()
            ->where(fn ($query) => $query->whereRaw('lower(address) = lower(?)', [$email->fromEmail])->orWhereRaw('lower(inbound_address) = lower(?)', [$email->fromEmail]))
            ->exists();
    }

    /**
     * Log a bounce on the ticket whose reply it returns; the result is the reason to drop the mail.
     */
    protected function recordBounce(InboundEmail $email): string
    {
        $comment = $this->threadedComment($email);

        if ($comment?->ticket === null) {
            return 'bounce for an unknown message';
        }

        $this->recordEmailBounce->handle($comment, $email->replyText());

        return 'bounce recorded';
    }

    /**
     * The comment this mail answers, matched on In-Reply-To and References.
     */
    protected function threadedComment(InboundEmail $email): ?TicketComment
    {
        $ids = array_values(array_filter([$email->inReplyTo, ...$email->references]));

        return $ids === [] ? null : TicketComment::withTrashed()->whereIn('message_id', $ids)->latest()->first();
    }

    /**
     * The ticket named by `[#1042]` in the subject. Anyone's reply lands on it, not just the
     * requester's; a sender who isn't an agent only adds a comment, which isn't emailed out.
     */
    protected function ticketBySubject(InboundEmail $email): ?Ticket
    {
        if (preg_match('/\[#(\d+)\]/', $email->subject, $match) !== 1) {
            return null;
        }

        return Ticket::query()->where('number', (int) $match[1])->first();
    }

    /**
     * The workspace's agent who sent a reply to the ticket, so it's added as their comment and
     * emailed to the requester. Only when the receiving server vouched for the From address and
     * the agent can work the ticket; otherwise the sender is treated as a contact.
     */
    protected function agent(InboundEmail $email, ?Ticket $ticket): ?User
    {
        if ($ticket === null || ! $email->hasAuthenticatedSender()) {
            return null;
        }

        $user = Workspace::current()?->users()->whereRaw('lower(email) = lower(?)', [$email->fromEmail])->first();

        return $user !== null && Gate::forUser($user)->allows('update', $ticket) ? $user : null;
    }

    protected function isOverRateLimit(Mailbox $mailbox, InboundEmail $email): bool
    {
        $key = "inbound-email:{$mailbox->workspace_id}:{$email->fromEmail}";

        if (RateLimiter::tooManyAttempts($key, $mailbox->workspace->settings->mailNewTicketsPerSenderPerHour)) {
            return true;
        }

        RateLimiter::hit($key, 3600);

        return false;
    }

    /**
     * @param  array{body: string, attachments: array<int, UploadedFile>, message_id: string}  $input
     */
    protected function reply(User|Contact $author, Ticket $ticket, array $input): TicketComment
    {
        return DB::transaction(function () use ($author, $ticket, $input): TicketComment {
            if ($author instanceof User) {
                // Agents can't set a message_id through AddTicketComment (or the API). The agent's
                // own one is stored for dedupe, and the copy emailed to the requester reuses it.
                $comment = $this->addTicketComment->handle($author, $ticket, Arr::except($input, 'message_id'));
                $comment->forceFill(['message_id' => $input['message_id']])->save();

                return $comment;
            }

            return $this->addTicketComment->handle($author, $ticket, $input);
        });
    }

    /**
     * The attachments as uploaded files, and a line for each one that was rejected.
     *
     * ponytail: each attachment is written to a temp file and wrapped as a test-mode UploadedFile
     * so it goes through the same validation and storage as a browser upload.
     *
     * @return array{0: array<int, UploadedFile>, 1: array<int, string>}
     */
    protected function attachments(InboundEmail $email): array
    {
        $files = [];
        $skipped = [];

        foreach ($email->attachments as $attachment) {
            $path = (string) tempnam(sys_get_temp_dir(), 'harbor-mail-');
            file_put_contents($path, $attachment['contents']);
            $file = new UploadedFile($path, $attachment['filename'], $attachment['mime'], null, true);

            if (count($files) < 20 && Validator::make(['file' => $file], ['file' => AddTicketAttachment::rules()])->passes()) {
                $files[] = $file;
            } else {
                $skipped[] = __('[The attachment :name was skipped.]', ['name' => $attachment['filename']]);
                @unlink($path);
            }
        }

        return [$files, $skipped];
    }
}
