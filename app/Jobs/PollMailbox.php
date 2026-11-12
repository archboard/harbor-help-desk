<?php

namespace App\Jobs;

use App\Actions\Email\ReceiveInboundEmail;
use App\Enums\MailboxDriver;
use App\Mail\Inbound\GraphMailboxClient;
use App\Mail\Inbound\ImapMailboxClient;
use App\Mail\Inbound\InboundEmail;
use App\Models\Mailbox;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
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

    public function handle(ReceiveInboundEmail $receiveInboundEmail): void
    {
        try {
            match ($this->mailbox->driver) {
                MailboxDriver::MICROSOFT => $this->pollGraph($receiveInboundEmail),
                default => $this->pollImap($receiveInboundEmail),
            };

            $this->mailbox->forceFill(['polled_at' => now(), 'last_error' => null])->save();
        } catch (Throwable $exception) {
            $this->mailbox->forceFill(['polled_at' => now(), 'last_error' => $exception->getMessage()])->save();
        }
    }

    /**
     * A first poll, or one after UIDVALIDITY changed, starts at the newest message instead of
     * importing the whole folder. After that, an unchanged UIDNEXT means one STATUS and done.
     */
    protected function pollImap(ReceiveInboundEmail $receiveInboundEmail): void
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
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Microsoft 365 mailboxes follow a Graph delta query instead. The first poll, or one after
     * the delta link expired, starts from now. The cursor is saved after every page (a next
     * link mid-way, the delta link at the end), so a failed poll resumes where it stopped.
     *
     * ponytail: delta also reports read, flagged and moved messages, which are fetched again and
     * skipped as duplicates by their Message-ID. Select `internetMessageId` and skip known ones
     * before fetching if those extra requests start to matter.
     */
    protected function pollGraph(ReceiveInboundEmail $receiveInboundEmail): void
    {
        $mailbox = $this->mailbox;
        $client = app()->makeWith(GraphMailboxClient::class, ['mailbox' => $mailbox]);

        if ($mailbox->graph_delta_link !== null) {
            try {
                $url = $mailbox->graph_delta_link;

                do {
                    $page = $client->page($url);

                    // A message that can't be downloaded fails the poll before the cursor moves past it.
                    foreach ($page['ids'] as $id) {
                        $this->receive($receiveInboundEmail, $client->raw($id), $id);
                    }

                    $mailbox->forceFill(['graph_delta_link' => $page['next_link'] ?? $page['delta_link']])->save();
                } while ($url = $page['next_link']);

                return;
            } catch (RequestException $exception) {
                // 410 Gone: the delta link expired, so start again from now.
                if ($exception->response->status() !== Response::HTTP_GONE) {
                    throw $exception;
                }
            }
        }

        $mailbox->forceFill(['graph_delta_link' => $client->start()])->save();
    }

    /**
     * One bad message is logged and skipped, so it can't block the mailbox forever.
     */
    protected function receive(ReceiveInboundEmail $receiveInboundEmail, string $raw, int|string $uid): void
    {
        try {
            $receiveInboundEmail->handle($this->mailbox, InboundEmail::fromMime($raw));
        } catch (Throwable $exception) {
            Log::warning('Inbound email failed to import', ['mailbox_id' => $this->mailbox->id, 'uid' => $uid, 'error' => $exception->getMessage()]);
            report($exception);
        }
    }
}
