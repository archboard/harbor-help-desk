<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use App\Enums\WorkspaceRole;
use Illuminate\Validation\Rule;

/**
 * Who to invite and as what. Owners can't be invited. `InviteMember` checks the email and
 * desk against the workspace.
 */
class InviteMemberData extends InputData
{
    public function __construct(
        public string $email,
        public WorkspaceRole $role,
        public ?string $desk_id = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(WorkspaceRole::class)->only([WorkspaceRole::ADMIN, WorkspaceRole::AGENT])],
            'desk_id' => ['nullable', 'string'],
        ];
    }
}
