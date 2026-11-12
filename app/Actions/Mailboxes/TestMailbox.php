<?php

namespace App\Actions\Mailboxes;

use App\Data\Mailboxes\MailboxCheckData;
use App\Data\Mailboxes\MailboxTestData;
use App\Enums\MailboxDriver;
use App\Mail\Inbound\GraphMailboxClient;
use App\Mail\Inbound\ImapMailboxClient;
use App\Mail\MailboxTestMail;
use App\Models\Mailbox;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;
use Throwable;

class TestMailbox
{
    /**
     * Check a saved mailbox's connections now: send a test email to the actor, and open the
     * inbox and count its messages. Failures are reported, never thrown or saved on the mailbox.
     */
    public function handle(User $actor, Mailbox $mailbox): MailboxTestData
    {
        Gate::forUser($actor)->authorize('update', $mailbox);

        return new MailboxTestData(
            sending: $mailbox->sendsMail() ? $this->check(fn (): string => $this->send($actor, $mailbox)) : null,
            receiving: $mailbox->receivesMail() && $mailbox->driver !== MailboxDriver::HOSTED
                ? $this->check(fn (): string => $this->receive($mailbox))
                : null,
        );
    }

    /**
     * @param  Closure(): string  $test
     */
    protected function check(Closure $test): MailboxCheckData
    {
        try {
            return new MailboxCheckData(true, $test());
        } catch (Throwable $exception) {
            return new MailboxCheckData(false, $exception->getMessage());
        }
    }

    protected function send(User $actor, Mailbox $mailbox): string
    {
        $mailbox->mailer()->to($actor->email)->send(new MailboxTestMail($mailbox));

        return __('Sent a test email to :email.', ['email' => $actor->email]);
    }

    protected function receive(Mailbox $mailbox): string
    {
        if ($mailbox->driver === MailboxDriver::MICROSOFT) {
            $count = app()->makeWith(GraphMailboxClient::class, ['mailbox' => $mailbox])->count();
        } else {
            $client = app()->makeWith(ImapMailboxClient::class, ['mailbox' => $mailbox]);

            try {
                $count = $client->status()['messages'];
            } finally {
                $client->disconnect();
            }
        }

        return trans_choice('Connected. The inbox has :count message.|Connected. The inbox has :count messages.', $count);
    }
}
