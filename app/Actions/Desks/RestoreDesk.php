<?php

namespace App\Actions\Desks;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RestoreDesk
{
    /**
     * Restore an archived desk, unless another active desk has taken its slug.
     */
    public function handle(User $actor, Desk $desk): Desk
    {
        Gate::forUser($actor)->authorize('restore', $desk);

        if (Desk::query()->where('workspace_id', $desk->workspace_id)->where('slug', $desk->slug)->exists()) {
            throw ValidationException::withMessages([
                'slug' => __('Another desk is using the slug ":slug".', ['slug' => $desk->slug]),
            ]);
        }

        $desk->restore();

        AuditEvent::record(AuditAction::DESK_RESTORED, $actor, $desk);

        return $desk;
    }
}
