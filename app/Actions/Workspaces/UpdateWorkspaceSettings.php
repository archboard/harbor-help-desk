<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\UpdateWorkspaceSettingsData;
use App\Data\Workspaces\WorkspaceSettings;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateWorkspaceSettings
{
    /**
     * Merge the provided settings into the workspace's. `null` resets a setting to its default.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Workspace $workspace, array $input): Workspace
    {
        Gate::forUser($actor)->authorize('update', $workspace);

        $unknown = array_diff(array_keys($input), array_keys((new WorkspaceSettings)->toArray()));

        if ($unknown !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unknown, __('This setting does not exist.')),
            );
        }

        $data = UpdateWorkspaceSettingsData::validateAndCreateWith($input, [
            'mail_notification_mailbox_id' => [
                'nullable',
                Rule::exists('mailboxes', 'id')->where('workspace_id', $workspace->id),
            ],
            'attachments_max_size_kb' => ['nullable', 'integer', 'max:'.config('harbor.attachments.max_size_kb')],
            'attachments_blocked_extensions.*' => ['string', 'regex:/^[a-z0-9]+$/', 'distinct'],
            // Private targets are a self-hosted option: the cloud can never switch them on.
            'automations_allow_private_urls' => ['sometimes', 'nullable', 'boolean', ...(config('harbor.multitenant') ? ['declined'] : [])],
        ]);

        $workspace->update([
            'settings' => [...$workspace->settings->toArray(), ...$data->filled()],
        ]);

        return $workspace;
    }
}
