<?php

namespace App\Actions\Exports;

use App\Enums\AuditAction;
use App\Enums\ExportStatus;
use App\Models\AuditEvent;
use App\Models\Export;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExport
{
    /**
     * Stream the export's zip. An expired export is gone (410) and one that isn't built yet is
     * not found (404).
     */
    public function handle(User $actor, Export $export): StreamedResponse
    {
        Gate::forUser($actor)->authorize('download', $export);

        abort_if($export->expires_at->isPast(), 410, __('This export has expired.'));
        abort_unless($export->status === ExportStatus::COMPLETED && $export->path !== null, 404);

        AuditEvent::record(AuditAction::EXPORT_DOWNLOADED, $actor, $export, ['export_id' => $export->getKey()]);

        return Storage::disk(config('harbor.attachments.disk'))->download($export->path, "harbor-export-{$export->getKey()}.zip");
    }
}
