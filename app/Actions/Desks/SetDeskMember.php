<?php

namespace App\Actions\Desks;

use App\Enums\DeskRole;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SetDeskMember
{
    /**
     * Add a workspace member to the desk, or change their role if they're already on it.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Desk $desk, User $member, array $input): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $desk);

        $validated = Validator::make($input, [
            'role' => ['required', Rule::enum(DeskRole::class)],
        ])->validate();

        if ($member->workspaceRole($desk->workspace) === null) {
            throw ValidationException::withMessages([
                'user' => __('Only members of this workspace can be added to its desks.'),
            ]);
        }

        $desk->users()->syncWithoutDetaching([
            $member->getKey() => ['role' => $validated['role'], 'workspace_id' => $desk->workspace_id],
        ]);
    }
}
