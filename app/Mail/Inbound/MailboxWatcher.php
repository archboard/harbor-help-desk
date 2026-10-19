<?php

namespace App\Mail\Inbound;

use App\Enums\MailboxDriver;
use App\Jobs\PollMailbox;
use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Landlord code behind `mailboxes:watch`: holds one IMAP connection open per mailbox in its
 * shard and sends STATUS on each every tick. When a folder's UIDNEXT or UIDVALIDITY moves, it
 * queues a PollMailbox with that mailbox's workspace current, so importing stays on one path.
 *
 * Full IMAP IDLE isn't used because a blocking IDLE needs one process per mailbox; held
 * connections let one process cycle through ~100 mailboxes every 5–10 seconds.
 */
class MailboxWatcher
{
    /**
     * @var Collection<string, Mailbox>
     */
    protected Collection $mailboxes;

    protected ?CarbonImmutable $loadedAt = null;

    /**
     * @var array<string, ImapMailboxClient>
     */
    protected array $clients = [];

    /**
     * The last UIDVALIDITY and UIDNEXT seen per mailbox, as `"{validity}:{next}"`.
     *
     * @var array<string, string>
     */
    protected array $lastSeen = [];

    /**
     * Reconnect backoff per mailbox after an error.
     *
     * @var array<string, array{retry_at: CarbonImmutable, delay: int}>
     */
    protected array $backoff = [];

    public function __construct(protected int $shard = 0, protected int $shards = 1)
    {
        $this->mailboxes = new Collection;
    }

    public function tick(): void
    {
        if ($this->loadedAt === null || $this->loadedAt->lte(now()->subMinute())) {
            $this->reload();
        }

        foreach ($this->mailboxes as $mailbox) {
            if (isset($this->backoff[$mailbox->id]) && $this->backoff[$mailbox->id]['retry_at']->isFuture()) {
                continue;
            }

            $this->check($mailbox);
        }
    }

    public function stop(): void
    {
        foreach ($this->clients as $client) {
            rescue(fn () => $client->disconnect(), report: false);
        }

        $this->clients = [];
    }

    /**
     * The mailboxes in this shard, refreshed every minute so new and deleted ones are picked up.
     */
    protected function reload(): void
    {
        $this->mailboxes = Mailbox::withoutGlobalScope('workspace')
            ->where('driver', '!=', MailboxDriver::HOSTED)
            ->with('workspace')
            ->get()
            ->filter(fn (Mailbox $mailbox): bool => crc32($mailbox->id) % $this->shards === $this->shard)
            ->keyBy('id');

        foreach (array_diff_key($this->clients, $this->mailboxes->all()) as $id => $client) {
            rescue(fn () => $client->disconnect(), report: false);
            unset($this->clients[$id], $this->lastSeen[$id], $this->backoff[$id]);
        }

        $this->loadedAt = now()->toImmutable();
    }

    protected function check(Mailbox $mailbox): void
    {
        try {
            $client = $this->clients[$mailbox->id] ??= app()->makeWith(ImapMailboxClient::class, ['mailbox' => $mailbox]);
            $status = $client->status();
            unset($this->backoff[$mailbox->id]);
        } catch (Throwable $exception) {
            $this->failed($mailbox, $exception);

            return;
        }

        $seen = "{$status['uid_validity']}:{$status['uid_next']}";
        $this->lastSeen[$mailbox->id] ??= $mailbox->imap_last_uid === null ? '' : "{$mailbox->imap_uid_validity}:".($mailbox->imap_last_uid + 1);

        // ponytail: a burst of more than one poll's 50 messages leaves the rest for the next
        // UIDNEXT change or the 5-minute backstop; have PollMailbox requeue itself if that's too slow.
        if ($this->lastSeen[$mailbox->id] !== $seen) {
            $mailbox->workspace->execute(function () use ($mailbox): void {
                PollMailbox::dispatch($mailbox);
            });
            $this->lastSeen[$mailbox->id] = $seen;
        }
    }

    /**
     * Drop the connection, save the error and retry after 30 seconds, doubling up to 5 minutes.
     */
    protected function failed(Mailbox $mailbox, Throwable $exception): void
    {
        if (isset($this->clients[$mailbox->id])) {
            rescue(fn () => $this->clients[$mailbox->id]->disconnect(), report: false);
            unset($this->clients[$mailbox->id]);
        }

        $delay = min(($this->backoff[$mailbox->id]['delay'] ?? 15) * 2, 300);
        $this->backoff[$mailbox->id] = ['retry_at' => now()->toImmutable()->addSeconds($delay), 'delay' => $delay];

        $mailbox->forceFill(['last_error' => $exception->getMessage()])->save();
    }
}
