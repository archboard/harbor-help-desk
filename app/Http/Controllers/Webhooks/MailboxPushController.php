<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Mailboxes\SubscribeMailboxPush;
use App\Enums\MailboxDriver;
use App\Http\Controllers\Controller;
use App\Jobs\PollMailbox;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Landlord code: Gmail and Microsoft Graph post here for every workspace. A notification only
 * says "look now", so each one queues a poll inside the mailbox's workspace.
 */
class MailboxPushController extends Controller
{
    /**
     * A Pub/Sub push subscription pointed at `?token={harbor.mail.push_secret}`. Always 204,
     * so Pub/Sub doesn't retry forever.
     */
    public function gmail(Request $request): Response
    {
        $secret = (string) config('harbor.mail.push_secret');

        if ($secret === '' || ! hash_equals($secret, (string) $request->query('token'))) {
            return response()->noContent();
        }

        $payload = json_decode((string) base64_decode((string) $request->input('message.data')), true);
        $address = strtolower((string) ($payload['emailAddress'] ?? ''));

        if ($address !== '') {
            // The same Gmail account connected to two workspaces polls in both.
            Mailbox::withoutGlobalScope('workspace')
                ->where('driver', MailboxDriver::GOOGLE)
                ->whereRaw('lower(address) = ?', [$address])
                ->with('workspace')
                ->get()
                ->each(fn (Mailbox $mailbox) => $this->poll($mailbox));
        }

        return response()->noContent();
    }

    /**
     * Echoes the `validationToken` handshake, then handles notifications and lifecycle events.
     */
    public function graph(Request $request): Response
    {
        if ($request->filled('validationToken')) {
            return response($request->query('validationToken'), 200, ['Content-Type' => 'text/plain']);
        }

        foreach ((array) $request->input('value', []) as $notification) {
            [$id, $hmac] = [...explode('.', (string) ($notification['clientState'] ?? ''), 2), ''];
            $mailbox = Mailbox::withoutGlobalScope('workspace')->with('workspace')->find($id);

            if ($mailbox === null
                || $mailbox->push_secret === null
                || ! hash_equals(SubscribeMailboxPush::clientState($mailbox, $mailbox->push_secret), "{$id}.{$hmac}")) {
                continue;
            }

            if (in_array($notification['lifecycleEvent'] ?? null, ['reauthorizationRequired', 'subscriptionRemoved'], true)) {
                defer(fn () => app(SubscribeMailboxPush::class)->handle($mailbox));
            } else {
                $this->poll($mailbox);
            }
        }

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    protected function poll(Mailbox $mailbox): void
    {
        $mailbox->workspace->execute(function () use ($mailbox): void {
            PollMailbox::dispatch($mailbox);
        });
    }
}
