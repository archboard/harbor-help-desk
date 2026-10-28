<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\UpdateProvisionedWorkspaceData;
use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use App\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\DB;

class UpdateProvisionedWorkspace
{
    /**
     * Landlord update of a workspace's name or subdomain. The subdomain is locked once the
     * workspace has members, since their links point at it. Changing it moves the hosted
     * mailbox to the new address.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(Workspace $workspace, array $input): Workspace
    {
        $data = UpdateProvisionedWorkspaceData::validateAndCreateWith($input, [
            'subdomain' => [
                'sometimes',
                SubdomainRules::unique($workspace),
                function (string $attribute, mixed $value, Closure $fail) use ($workspace): void {
                    if ($value !== $workspace->subdomain && $workspace->hasMembers()) {
                        $fail(__('The subdomain can no longer change once the workspace has members.'));
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($workspace, $data): void {
            $workspace->update($data->filled());

            if ($workspace->wasChanged('subdomain')) {
                $this->renameHostedMailbox($workspace);
            }
        });

        return $workspace;
    }

    private function renameHostedMailbox(Workspace $workspace): void
    {
        $domain = config('harbor.mail.inbound_domain');

        if (! config('harbor.multitenant') || blank($domain)) {
            return;
        }

        $address = "{$workspace->subdomain}@{$domain}";

        $workspace->execute(fn () => Mailbox::query()
            ->where('driver', MailboxDriver::HOSTED)
            ->update(['address' => $address, 'inbound_address' => $address]));
    }
}
