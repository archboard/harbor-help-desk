<?php

namespace App\Data\Desks;

use App\Enums\DeskRole;
use App\Models\DeskUser;
use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * A user on a desk, loaded through `Desk::users()` so the pivot holds their role.
 */
class DeskMemberData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?DeskRole $role,
    ) {}

    public static function fromModel(User $user): self
    {
        $membership = $user->getRelation('pivot');

        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $membership instanceof DeskUser ? $membership->role : null,
        );
    }
}
