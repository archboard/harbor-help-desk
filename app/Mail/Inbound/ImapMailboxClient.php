<?php

namespace App\Mail\Inbound;

use App\Models\Mailbox;
use DirectoryTree\ImapEngine\Folder;
use DirectoryTree\ImapEngine\Mailbox as ImapMailbox;

/**
 * A read-only IMAP connection to one of the workspace's own mailboxes. Messages are fetched
 * with BODY.PEEK, so nothing is marked as read.
 */
class ImapMailboxClient
{
    protected ?ImapMailbox $imap = null;

    public function __construct(protected Mailbox $mailbox) {}

    /**
     * The folder's next UID and UID validity, from a single STATUS command.
     *
     * @return array{uid_validity: int, uid_next: int}
     */
    public function status(): array
    {
        $values = array_change_key_case($this->folder()->status(), CASE_UPPER);

        return ['uid_validity' => (int) ($values['UIDVALIDITY'] ?? 0), 'uid_next' => (int) ($values['UIDNEXT'] ?? 0)];
    }

    /**
     * The raw messages after the given UID, oldest first, at most `$limit` per call.
     *
     * @return iterable<int, string> raw MIME keyed by UID
     */
    public function fetchSince(int $lastUid, int $limit = 50): iterable
    {
        $query = $this->folder()->messages()->leaveUnread()->withHeaders()->withBody()->oldest()->limit($limit);
        $query->uid($lastUid + 1, INF);

        foreach ($query->get() as $message) {
            // `UID n:*` always matches the newest message, even when its UID is below n.
            if ($message->uid() > $lastUid) {
                yield $message->uid() => (string) $message;
            }
        }
    }

    public function disconnect(): void
    {
        $this->imap?->disconnect();
        $this->imap = null;
    }

    protected function imap(): ImapMailbox
    {
        if ($this->imap === null) {
            $settings = $this->mailbox->connection('imap');

            $this->imap = new ImapMailbox([
                'host' => $settings['host'] ?? '',
                'port' => (int) ($settings['port'] ?? 993),
                'encryption' => $settings['encryption'] ?? 'ssl',
                'username' => $settings['username'] ?? '',
                'password' => $settings['password'] ?? '',
                'authentication' => $this->mailbox->driver->usesOAuth() ? 'oauth' : 'plain',
                'timeout' => 30,
            ]);
            $this->imap->connect();
        }

        return $this->imap;
    }

    protected function folder(): Folder
    {
        return new Folder($this->imap(), $this->mailbox->settings['imap']['folder'] ?? 'INBOX');
    }
}
