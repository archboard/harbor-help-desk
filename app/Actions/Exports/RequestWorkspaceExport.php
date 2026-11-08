<?php

namespace App\Actions\Exports;

use App\Enums\AuditAction;
use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Jobs\BuildExport;
use App\Models\AuditEvent;
use App\Models\Export;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestWorkspaceExport
{
    /**
     * Queue a zip of the whole current workspace. The requester is notified when it's ready.
     */
    public function handle(User $actor): Export
    {
        Gate::forUser($actor)->authorize('create', Export::class);

        $workspace = Workspace::current();

        return DB::transaction(function () use ($actor, $workspace): Export {
            $export = Export::create([
                'type' => ExportType::WORKSPACE,
                'requested_by' => $actor->getKey(),
                'status' => ExportStatus::PENDING,
                'expires_at' => now()->addDays(Export::LIFETIME_DAYS),
            ]);

            AuditEvent::record(AuditAction::WORKSPACE_EXPORT_REQUESTED, $actor, $workspace, ['export_id' => $export->getKey()]);

            BuildExport::dispatch($export)->afterCommit();

            return $export;
        });
    }
}
