<?php

namespace App\Mail;

use App\Actions\Mailboxes\RefreshMailboxToken;
use App\Mail\Inbound\GraphMailboxClient;
use App\Models\Mailbox;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends as a Microsoft 365 mailbox through Graph's `sendMail`, which takes the whole message as
 * base64 MIME, so our Message-ID, In-Reply-To and References headers go out as built. Graph
 * keeps a copy in the mailbox's Sent Items. No SMTP AUTH is needed on the tenant.
 *
 * ponytail: Graph takes MIME up to about 4 MB, so a reply with larger attachments fails and the
 * job reports it; a draft plus an attachment upload session lifts that, but drops the threading
 * headers. Graph reads recipients from the MIME headers, and Symfony strips Bcc before this
 * runs, so Bcc recipients wouldn't get a copy; nothing in Harbor sends Bcc today.
 */
class GraphTransport extends AbstractTransport
{
    public function __construct(protected Mailbox $mailbox)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        Http::withToken(app(RefreshMailboxToken::class)->handle($this->mailbox))
            ->withBody(base64_encode($message->toString()), 'text/plain')
            ->timeout(30)
            ->post(GraphMailboxClient::BASE_URL.'/me/sendMail')
            ->throw();
    }

    public function __toString(): string
    {
        return 'graph';
    }
}
