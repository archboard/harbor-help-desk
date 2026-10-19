<?php

namespace App\Jobs;

use App\Actions\Email\ReceiveInboundEmail;
use App\Mail\Inbound\ImapMailboxClient;
use App\Mail\Inbound\InboundEmail;
use App\Models\Mailbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Import new mail from one of the workspace's own mailboxes. Runs on its own `mail-poll` queue
 * so polling never delays reminders or outbound mail.
 */
class PollMailbox implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public Mailbox $mailbox)
    {
        $this->onQueue('mail-poll');
    }

    public function uniqueId(): string
    {
        return $this->mailbox->id;
    }

    /**
     * A first poll, or one after UIDVALIDITY changed, starts at the newest message instead of
     * importing the whole folder. After that, an unchanged UIDNEXT means one STATUS and done.
     */
    public function handle(ReceiveInboundEmail $receiveInboundEmail): void
    {
        $mailbox = $this->mailbox;
        $client = app()->makeWith(ImapMailboxClient::class, ['mailbox' => $mailbox]);

        try {
            $status = $client->status();

            if ($mailbox->imap_last_uid === null || $mailbox->imap_uid_validity !== $status['uid_validity']) {
                $mailbox->forceFill(['imap_uid_validity' => $status['uid_validity'], 'imap_last_uid' => $status['uid_next'] - 1]);
            } elseif ($status['uid_next'] > $mailbox->imap_last_uid + 1) {
                foreach ($client->fetchSince($mailbox->imap_last_uid) as $uid => $raw) {
                    $this->receive($receiveInboundEmail, $raw, $uid);
                    $mailbox->forceFill(['imap_last_uid' => $uid])->save();
                }
            }

            $mailbox->forceFill(['polled_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $mailbox->forceFill(['polled_at' => now(), 'last_error' => $exception->getMessage()])->save();
        } finally {
            $client->disconnect();
        }
    }

    /**
     * One bad message is logged and skipped, so it can't block the mailbox forever.
     */
    protected function receive(ReceiveInboundEmail $receiveInboundEmail, string $raw, int $uid): void
    {
        try {
            $receiveInboundEmail->handle($this->mailbox, InboundEmail::fromMime($raw));
        } catch (Throwable $exception) {
            Log::warning('Inbound email failed to import', ['mailbox_id' => $this->mailbox->id, 'uid' => $uid, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }
}
