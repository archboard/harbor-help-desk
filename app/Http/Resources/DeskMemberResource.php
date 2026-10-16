<?php

namespace App\Http\Resources;

use App\Models\DeskUser;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A user on a desk, loaded through `Desk::users()` so the pivot holds their role.
 *
 * @mixin User
 */
class DeskMemberResource extends UserResource
{
    /**
     * @return array{id: string, name: string, email: string, role: string|null}
     */
    public function toArray(Request $request): array
    {
        $membership = $this->resource->getRelation('pivot');

        return [
            ...parent::toArray($request),
            'role' => $membership instanceof DeskUser ? $membership->role->value : null,
        ];
    }
}
