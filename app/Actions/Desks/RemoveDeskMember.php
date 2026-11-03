<?php

namespace App\Actions\Desks;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RemoveDeskMember
{
    public function handle(User $actor, Desk $desk, User $member): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $desk);

        $desk->users()->detach($member->getKey());

        AuditEvent::record(AuditAction::DESK_MEMBER_REMOVED, $actor, $desk, ['user_id' => $member->getKey()]);
    }
}
