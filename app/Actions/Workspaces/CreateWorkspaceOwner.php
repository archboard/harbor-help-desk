<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\CreateWorkspaceOwnerData;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateWorkspaceOwner
{
    /**
     * Claim an unclaimed workspace: create its first agent, verified, as the owner. The
     * workspace row is locked, so two people claiming at once can't both become owners.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(Workspace $workspace, array $input): User
    {
        $data = CreateWorkspaceOwnerData::validateAndCreate($input);

        return DB::transaction(function () use ($workspace, $data): User {
            Workspace::query()->whereKey($workspace->getKey())->lockForUpdate()->sole();

            if ($workspace->hasMembers()) {
                throw ValidationException::withMessages(['email' => __('This workspace already has an owner.')]);
            }

            $user = User::create([...$data->filled(), 'email_verified_at' => now()]);
            $workspace->users()->attach($user, ['role' => WorkspaceRole::OWNER]);

            return $user;
        });
    }
}
