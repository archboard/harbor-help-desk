<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Email\ReceiveInboundEmail;
use App\Actions\Email\RecordEmailBounce;
use App\Enums\MailboxDriver;
use App\Http\Controllers\Controller;
use App\Mail\Inbound\InboundEmail;
use App\Models\Mailbox;
use App\Models\TicketComment;
use App\Models\Workspace;
use App\TenantFinders\SingleWorkspaceFinder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Landlord code: Postmark posts here for every workspace, so each request finds its workspace
 * and runs inside it. Unknown recipients still get a 200 so Postmark stops retrying.
 */
class PostmarkWebhookController extends Controller
{
    /**
     * Mail to a hosted address (`{subdomain}@{inbound_domain}`). Needs "Include raw email
     * content" turned on for the inbound stream.
     */
    public function inbound(Request $request, ReceiveInboundEmail $receiveInboundEmail): Response
    {
        $workspace = $this->workspaceFor($this->hostedRecipient($request), $request);
        $mailbox = $workspace?->execute(fn (): ?Mailbox => Mailbox::query()->where('driver', MailboxDriver::HOSTED)->first());
        $raw = $request->string('RawEmail')->toString();

        if ($workspace === null || $mailbox === null || $raw === '') {
            Log::info('Postmark inbound email ignored', ['message_id' => $request->input('MessageID'), 'has_raw_email' => $raw !== '']);

            return response('');
        }

        $workspace->execute(fn () => $receiveInboundEmail->handle($mailbox, InboundEmail::fromMime($raw)));

        return response('');
    }

    /**
     * A bounce of a reply sent through Postmark, which echoes the metadata set on it.
     */
    public function bounce(Request $request, RecordEmailBounce $recordEmailBounce): Response
    {
        $workspace = Workspace::query()->find($request->string('Metadata.workspace_id')->toString());

        $workspace?->execute(function () use ($request, $recordEmailBounce): void {
            $comment = TicketComment::withTrashed()->find($request->string('Metadata.comment_id')->toString());

            if ($comment !== null) {
                $recordEmailBounce->handle($comment, trim("{$request->input('Email')}: {$request->input('Description')}", ': '));
            }
        });

        return response('');
    }

    /**
     * The first recipient on the inbound domain, preferring the envelope recipient.
     */
    protected function hostedRecipient(Request $request): ?string
    {
        $domain = '@'.strtolower((string) config('harbor.mail.inbound_domain'));

        return collect([
            $request->input('OriginalRecipient'),
            ...Arr::pluck((array) $request->input('ToFull', []), 'Email'),
            ...Arr::pluck((array) $request->input('CcFull', []), 'Email'),
        ])
            ->map(fn (mixed $address): string => strtolower((string) $address))
            ->first(fn (string $address): bool => $domain !== '@' && str_ends_with($address, $domain));
    }

    /**
     * The workspace whose subdomain is the local part (ignoring `+tag`), or the single workspace
     * when self-hosted.
     */
    protected function workspaceFor(?string $recipient, Request $request): ?Workspace
    {
        if ($recipient === null) {
            return null;
        }

        if (! config('harbor.multitenant')) {
            return app(SingleWorkspaceFinder::class)->findForRequest($request);
        }

        return Workspace::query()->where('subdomain', Str::before(Str::before($recipient, '@'), '+'))->first();
    }
}
