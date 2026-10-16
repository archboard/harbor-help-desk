<?php

namespace App\Actions\Desks;

use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RemoveDeskMember
{
    public function handle(User $actor, Desk $desk, User $member): void
    {
        Gate::forUser($actor)->authorize('manageMembers', $desk);

        $desk->users()->detach($member->getKey());
    }
}
