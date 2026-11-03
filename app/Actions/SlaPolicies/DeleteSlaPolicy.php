<?php

namespace App\Actions\SlaPolicies;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteSlaPolicy
{
    /**
     * Desks using the policy fall back to no SLA.
     */
    public function handle(User $actor, SlaPolicy $slaPolicy): void
    {
        Gate::forUser($actor)->authorize('delete', $slaPolicy);

        $slaPolicy->delete();

        AuditEvent::record(AuditAction::SLA_POLICY_DELETED, $actor, $slaPolicy);
    }
}
