<?php

namespace App\Actions\Automations;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteAutomation
{
    public function handle(User $actor, Automation $automation): void
    {
        Gate::forUser($actor)->authorize('delete', $automation);

        $automation->delete();

        AuditEvent::record(AuditAction::AUTOMATION_DELETED, $actor, $automation);
    }
}
