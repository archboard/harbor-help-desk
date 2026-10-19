<?php

namespace App\Actions\Mailboxes;

use App\Enums\MailboxDriver;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Workspace;

class CreateHostedMailbox
{
    /**
     * Give the workspace its mailbox on Harbor's inbound domain: `{subdomain}@domain` in the
     * cloud, `support@domain` self-hosted. Does nothing when no inbound domain is configured.
     */
    public function handle(Workspace $workspace, Desk $defaultDesk): ?Mailbox
    {
        $domain = config('harbor.mail.inbound_domain');

        if (blank($domain)) {
            return null;
        }

        $localPart = config('harbor.multitenant') ? $workspace->subdomain : 'support';
        $address = "{$localPart}@{$domain}";

        return $workspace->mailboxes()->create([
            'default_desk_id' => $defaultDesk->getKey(),
            'driver' => MailboxDriver::HOSTED,
            'name' => $workspace->name,
            'address' => $address,
            'inbound_address' => $address,
        ]);
    }
}
