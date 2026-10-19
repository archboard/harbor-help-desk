<?php

namespace App\Console\Commands;

use App\Mail\Inbound\MailboxWatcher;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

#[Signature('mailboxes:watch {--shard=0 : This process\'s shard, from 0} {--shards=1 : How many watch processes share the mailboxes} {--max-time=3600 : Seconds to run before exiting so the supervisor restarts it}')]
#[Description('Check connected mailboxes for new mail every few seconds over held-open IMAP connections')]
class WatchMailboxes extends Command
{
    /**
     * Set by SIGTERM or SIGINT so the loop finishes its tick and exits cleanly.
     */
    protected bool $stopping = false;

    public function handle(): int
    {
        if (! config('harbor.mail.watch')) {
            $this->components->error('Set HARBOR_MAIL_WATCH=true first, so the scheduled poll becomes a backstop.');

            return self::FAILURE;
        }

        $shards = max(1, (int) $this->option('shards'));
        $watcher = new MailboxWatcher((int) $this->option('shard') % $shards, $shards);
        $deadline = now()->addSeconds((int) $this->option('max-time'));

        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->stopping = true;
        });

        while ($this->running($deadline)) {
            $watcher->tick();

            for ($second = 0; $second < config('harbor.mail.watch_interval') && $this->running($deadline); $second++) {
                Sleep::for(1)->second();
            }
        }

        $watcher->stop();

        return self::SUCCESS;
    }

    /**
     * Until the deadline or a stop signal; both change between calls.
     *
     * @phpstan-impure
     */
    protected function running(CarbonInterface $deadline): bool
    {
        return ! $this->stopping && now()->lt($deadline);
    }
}
