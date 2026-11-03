<?php

namespace App\Actions\Exports;

use App\Enums\AuditAction;
use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Jobs\BuildExport;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\Export;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestContactExport
{
    /**
     * Queue a zip of everything held about the contact. The requester is notified when it's ready.
     */
    public function handle(User $actor, Contact $contact): Export
    {
        Gate::forUser($actor)->authorize('create', Export::class);

        return DB::transaction(function () use ($actor, $contact): Export {
            $export = Export::create([
                'type' => ExportType::CONTACT,
                'subject_id' => $contact->getKey(),
                'requested_by' => $actor->getKey(),
                'status' => ExportStatus::PENDING,
                'expires_at' => now()->addDays(Export::LIFETIME_DAYS),
            ]);

            AuditEvent::record(AuditAction::CONTACT_EXPORT_REQUESTED, $actor, $contact, ['export_id' => $export->getKey()]);

            BuildExport::dispatch($export)->afterCommit();

            return $export;
        });
    }
}
