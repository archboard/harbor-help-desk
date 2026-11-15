<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\OnboardingData;
use App\Data\Workspaces\UpdateWorkspaceSettingsData;
use App\Data\Workspaces\WorkspaceSettings;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Arr;
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

        $unknown = array_diff(array_keys($input), [...array_keys((new WorkspaceSettings)->toArray()), 'microsoft_client_secret']);

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
            'onboarding_skipped_steps.*' => ['string', Rule::in(OnboardingData::STEPS), 'distinct'],
            // Private targets are a self-hosted option: the cloud can never switch them on.
            'automations_allow_private_urls' => ['sometimes', 'nullable', 'boolean', ...(config('harbor.multitenant') ? ['declined'] : [])],
        ]);

        $settings = $data->filled();

        // The secret is encrypted in its own column; `null` removes it.
        if (array_key_exists('microsoft_client_secret', $settings)) {
            $workspace->microsoft_client_secret = Arr::pull($settings, 'microsoft_client_secret');
        }

        $workspace->update([
            'settings' => [...$workspace->settings->toArray(), ...$settings],
        ]);

        AuditEvent::record(AuditAction::WORKSPACE_SETTINGS_UPDATED, $actor, $workspace, ['changed' => array_keys($data->filled())]);

        return $workspace;
    }
}
