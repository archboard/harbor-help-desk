<?php

namespace App\Mail;

use App\Models\Workspace;
use App\TenantFinders\SingleWorkspaceFinder;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The default mail transport. Each message goes out through the notification mailbox of the
 * current workspace (the only one when self-hosted), From that mailbox. Outside a workspace, or
 * with no mailbox, it goes through the platform mailer unchanged. Nothing tenant-specific is
 * kept on the instance, since Laravel caches it across workspaces in long-running workers.
 */
class WorkspaceTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        $workspace = Workspace::current()
            ?? (config('harbor.multitenant') ? null : app(SingleWorkspaceFinder::class)->findForRequest(request()));
        $mailbox = $workspace?->notificationMailbox();

        if (! $mailbox || ! $email instanceof Email) {
            Mail::mailer(config('harbor.mail.platform_mailer'))->getSymfonyTransport()->send($email, $message->getEnvelope());

            return;
        }

        $from = $mailbox->fromAddress();
        $email->from(new Address($from->address, (string) $from->name));

        $mailbox->transport()->send($email, new Envelope($email->getFrom()[0], $message->getEnvelope()->getRecipients()));
    }

    public function __toString(): string
    {
        return 'workspace';
    }
}
