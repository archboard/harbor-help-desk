<?php

namespace App\Mail\Inbound;

use App\Actions\Mailboxes\RefreshMailboxToken;
use App\Models\Mailbox;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * A read-only Microsoft Graph connection to a Microsoft 365 mailbox's Inbox. New mail is found
 * with a delta query, and each message is fetched as raw MIME, so nothing is marked as read.
 */
class GraphMailboxClient
{
    public const string BASE_URL = 'https://graph.microsoft.com/v1.0';

    public function __construct(protected Mailbox $mailbox) {}

    /**
     * A delta link that only reports mail received from now on, so the first poll doesn't
     * import the whole Inbox.
     */
    public function start(): string
    {
        $url = self::BASE_URL."/me/mailFolders('Inbox')/messages/delta?".Arr::query([
            '$select' => 'id',
            '$filter' => 'receivedDateTime ge '.now()->toIso8601ZuluString(),
        ]);

        do {
            $page = $this->page($url);
        } while ($url = $page['next_link']);

        return (string) $page['delta_link'];
    }

    /**
     * One page of a delta query: the ids of messages added or changed, and either the link to
     * the next page or, on the last one, the delta link for the next poll. A 410 means the
     * delta link expired.
     *
     * @return array{ids: list<string>, next_link: ?string, delta_link: ?string}
     */
    public function page(string $url): array
    {
        $page = $this->request()->withHeader('Prefer', 'odata.maxpagesize=50')->get($url)->throw()->json();
        $ids = [];

        foreach ($page['value'] ?? [] as $message) {
            if (! isset($message['@removed'])) {
                $ids[] = (string) $message['id'];
            }
        }

        return [
            'ids' => $ids,
            'next_link' => is_string($page['@odata.nextLink'] ?? null) ? $page['@odata.nextLink'] : null,
            'delta_link' => is_string($page['@odata.deltaLink'] ?? null) ? $page['@odata.deltaLink'] : null,
        ];
    }

    /**
     * The message as raw MIME.
     */
    public function raw(string $id): string
    {
        return $this->request()->get(self::BASE_URL.'/me/messages/'.rawurlencode($id).'/$value')->throw()->body();
    }

    protected function request(): PendingRequest
    {
        return Http::withToken(app(RefreshMailboxToken::class)->handle($this->mailbox))->timeout(30);
    }
}
