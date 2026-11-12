<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use App\Enums\WorkspaceRole;
use Illuminate\Validation\Rule;

/**
 * The role to give a member. `UpdateMemberRole` checks it against who is asking.
 */
class UpdateMemberRoleData extends InputData
{
    public function __construct(
        public WorkspaceRole $role,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ];
    }
}
