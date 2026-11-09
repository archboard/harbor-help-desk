<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\ImportWorkspaceData;
use App\Enums\AuditAction;
use App\Jobs\BuildExport;
use App\Jobs\RunWorkspaceImport;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ImportWorkspace
{
    /**
     * Check the uploaded export zip and queue its import into the current workspace, which must
     * not have any tickets or contacts yet. The requester is notified when it's done.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): void
    {
        $workspace = Workspace::current();

        Gate::forUser($actor)->authorize('import', $workspace);

        $file = ImportWorkspaceData::validateAndCreateWith($input, [
            'file' => [$this->workspaceIsEmpty(...), $this->exportIsCompatible(...)],
        ])->file;

        $disk = Storage::disk(config('harbor.attachments.disk'));
        $path = $disk->putFileAs("{$workspace->getKey()}/imports", $file, Str::ulid().'.zip');

        if ($path === false) {
            throw new RuntimeException(__('The export file could not be stored.'));
        }

        try {
            DB::transaction(function () use ($actor, $workspace, $path): void {
                AuditEvent::record(AuditAction::WORKSPACE_IMPORT_REQUESTED, $actor, $workspace);

                RunWorkspaceImport::dispatch($path, $actor->getKey())->afterCommit();
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }

    /**
     * Rule: imports only go into a workspace without tickets (trashed ones included) or contacts.
     */
    protected function workspaceIsEmpty(string $attribute, mixed $value, Closure $fail): void
    {
        if (self::hasTicketsOrContacts()) {
            $fail(__('Only a workspace without tickets or contacts can be imported into.'));
        }
    }

    public static function hasTicketsOrContacts(): bool
    {
        $workspaceId = Workspace::current()->getKey();

        return DB::table('tickets')->where('workspace_id', $workspaceId)->exists()
            || DB::table('contacts')->where('workspace_id', $workspaceId)->exists();
    }

    /**
     * Rule: a zip with a version 1 manifest, written by this version of the app, that holds a file for every table.
     */
    protected function exportIsCompatible(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($value->getRealPath(), ZipArchive::RDONLY) !== true) {
            $fail(__('The file is not a valid export.'));

            return;
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        if (! is_array($manifest) || ! is_string($manifest['workspace_id'] ?? null) || ! is_array($manifest['tables'] ?? null)) {
            $fail(__('The file is not a valid export.'));
        } elseif (($manifest['version'] ?? null) !== 1) {
            $fail(__('This export has an unsupported format version.'));
        } elseif (($manifest['migration'] ?? null) !== DB::table('migrations')->orderByDesc('id')->value('migration')) {
            $fail(__('This export was made by a different version of Harbor. Update both installations to the same version and export again.'));
        } elseif (array_filter(array_keys(BuildExport::WORKSPACE_TABLES), fn (string $table): bool => $zip->locateName("$table.jsonl") === false || ! is_int($manifest['tables'][$table] ?? null)) !== []) {
            $fail(__('The export is incomplete.'));
        }

        $zip->close();
    }
}
