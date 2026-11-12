<?php

namespace App\Data\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceUser;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * A user in the current workspace, loaded through `Workspace::users()` so the pivot holds their
 * role, with `desks_count` for the number of desks they are on.
 */
class WorkspaceMemberData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public WorkspaceRole $role,
        public int $deskCount,
        public ?CarbonImmutable $joinedAt,
    ) {}

    public static function fromModel(User $user): self
    {
        $membership = $user->getRelation('pivot');
        assert($membership instanceof WorkspaceUser);

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $membership->role,
            deskCount: (int) $user->getAttribute('desks_count'),
            joinedAt: $membership->created_at,
        );
    }
}
