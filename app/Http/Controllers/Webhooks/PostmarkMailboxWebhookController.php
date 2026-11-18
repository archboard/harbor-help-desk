<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Email\ReceiveInboundEmail;
use App\Enums\MailboxDriver;
use App\Http\Controllers\Controller;
use App\Mail\Inbound\InboundEmail;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Landlord code: the inbound webhook of a workspace's own Postmark server, at the secret URL
 * of one Postmark mailbox. Needs "Include raw email content" turned on for the inbound stream.
 */
class PostmarkMailboxWebhookController extends Controller
{
    public function __invoke(Request $request, string $mailbox, string $token, ReceiveInboundEmail $receiveInboundEmail): Response
    {
        $mailbox = Mailbox::withoutGlobalScope('workspace')->with('workspace')->where('driver', MailboxDriver::POSTMARK)->find($mailbox);

        abort_if($mailbox?->push_secret === null || ! hash_equals($mailbox->push_secret, $token), Response::HTTP_NOT_FOUND);

        $raw = $request->string('RawEmail')->toString();

        // Postmark's test payload has no raw email; a 200 tells it the URL works.
        if ($raw === '') {
            Log::info('Postmark inbound email without raw content ignored', ['mailbox_id' => $mailbox->id, 'message_id' => $request->input('MessageID')]);

            return response('');
        }

        $mailbox->workspace->execute(fn () => $receiveInboundEmail->handle($mailbox, InboundEmail::fromMime($raw)));

        return response('');
    }
}
