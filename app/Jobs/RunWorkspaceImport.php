<?php

namespace App\Jobs;

use App\Actions\Workspaces\ImportWorkspace;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceImportFinishedNotification;
use Generator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Imports an export zip (see {@see BuildExport}) into the current workspace, which must be
 * tenant-aware by the multitenancy default. Rows keep their original ids, which are globally unique,
 * so importing into the database the export came from fails on the first id collision.
 *
 * Everything happens in one transaction: the workspace's default desk and mailbox are replaced by the
 * exported ones, and any failure rolls it all back and deletes the files already copied.
 *
 * - `workspaces`: not inserted. Only the settings and the ticket counter are applied.
 * - Users are matched by email or created without a usable password. Matched users keep
 *   their local id, so every exported user id, in any column, JSON or text, is replaced by the local one.
 *   The old workspace id is replaced by the current one the same way (paths, audit subjects).
 * - Mailboxes come in disconnected (no credentials, no push subscription) and automation webhook
 *   tokens are left unset, so an admin has to reconnect and regenerate them.
 */
class RunWorkspaceImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public const int CHUNK_SIZE = 500;

    /**
     * @param  string  $path  The uploaded zip on the attachments disk.
     */
    public function __construct(public string $path, public string $requestedBy) {}

    public function handle(): void
    {
        $workspace = Workspace::current();
        $requester = User::query()->find($this->requestedBy);
        $disk = Storage::disk(config('harbor.attachments.disk'));
        $localZip = (string) tempnam(sys_get_temp_dir(), 'import');
        $copied = [];

        try {
            file_put_contents($localZip, $disk->readStream($this->path));

            $zip = new ZipArchive;
            $zip->open($localZip, ZipArchive::RDONLY);

            $counts = DB::transaction(function () use ($zip, $workspace, &$copied): array {
                return $this->import($zip, $workspace, $copied);
            });
            $zip->close();

            AuditEvent::record(AuditAction::WORKSPACE_IMPORTED, $requester, $workspace, ['tables' => $counts]);
            $requester?->notify(new WorkspaceImportFinishedNotification(true));
        } catch (Throwable $exception) {
            $disk->delete($copied);

            report($exception);

            // Only our own RuntimeExceptions carry a message that is safe to show: others can hold row values or SQL.
            $message = $exception::class === RuntimeException::class
                ? $exception->getMessage()
                : __('The data could not be imported. An id may already exist in this installation, or the file holds invalid data.');
            AuditEvent::record(AuditAction::WORKSPACE_IMPORT_FAILED, $requester, $workspace, ['exception' => $exception::class]);
            $requester?->notify(new WorkspaceImportFinishedNotification(false, $message));
        } finally {
            @unlink($localZip);
            $disk->delete($this->path);
        }
    }

    /**
     * @param  list<string>  $copied  Filled with the attachment files written, for the caller to delete on failure.
     * @return array<string, int> The rows inserted per table.
     */
    protected function import(ZipArchive $zip, Workspace $workspace, array &$copied): array
    {
        $workspaceId = $workspace->getKey();
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);

        // Another import may have finished since the request was validated.
        if (ImportWorkspace::hasTicketsOrContacts()) {
            throw new RuntimeException(__('Only a workspace without tickets or contacts can be imported into.'));
        }

        $this->deleteDefaults($workspaceId);

        $replacements = [$manifest['workspace_id'] => $workspaceId];
        $existingMembers = DB::table('workspace_user')->where('workspace_id', $workspaceId)->pluck('user_id')->flip();
        $counts = [];

        foreach (array_keys(BuildExport::WORKSPACE_TABLES) as $table) {
            if ($table === 'workspaces') {
                continue;
            }

            $inserted = 0;
            $chunk = [];

            foreach ($this->rows($zip, $table) as $row) {
                if ($table === 'users') {
                    $replacements += $this->importUser($row);
                    $inserted++;

                    continue;
                }

                $row = $this->replaceIds($row, $replacements);
                $row['workspace_id'] = $workspaceId;

                if ($table === 'workspace_user' && isset($existingMembers[$row['user_id']])) {
                    $inserted++;

                    continue;
                }

                $chunk[] = $this->prepare($table, $row, $zip, $workspaceId, $copied);
                $inserted++;

                if (count($chunk) === self::CHUNK_SIZE) {
                    DB::table($table)->insert($chunk);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                DB::table($table)->insert($chunk);
            }

            if ($inserted !== ($manifest['tables'][$table] ?? null)) {
                throw new RuntimeException(__('The export is corrupt: :table has a different number of rows than its manifest says.', ['table' => $table]));
            }

            $counts[$table] = $inserted;
        }

        $this->linkEventsToRuns($zip);
        $this->assertReferencesStayInWorkspace($workspaceId);
        $this->applyWorkspace($zip, $workspaceId, $replacements);

        return $counts;
    }

    /**
     * Remove what creating the workspace made, which the export replaces: the default desk and the
     * mailboxes that deliver to it (their rows would collide on slug and address).
     */
    protected function deleteDefaults(string $workspaceId): void
    {
        $deskIds = DB::table('desks')->where('workspace_id', $workspaceId)->where('slug', 'default')->pluck('id');

        DB::table('mailboxes')->where('workspace_id', $workspaceId)->whereIn('default_desk_id', $deskIds)->delete();
        DB::table('desks')->whereIn('id', $deskIds)->delete();
    }

    /**
     * Find the local user for an exported one (same email, else same id) or create it without a
     * usable password. Returns the id replacement to add when the local id differs.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    protected function importUser(array $row): array
    {
        $localId = DB::table('users')->whereRaw('lower(email) = ?', [Str::lower($row['email'])])->value('id');

        if ($localId === null) {
            // An id that belongs to another user collides here and fails the import.
            DB::table('users')->insert([...$row, 'password' => Hash::make(Str::random(64))]);
            $localId = $row['id'];
        }

        return $localId === $row['id'] ? [] : [$row['id'] => $localId];
    }

    /**
     * Replace the old workspace id and the exported user ids in every string value, which covers
     * foreign keys, morph ids, JSON columns, mention tokens and paths in one go.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $replacements
     * @return array<string, mixed>
     */
    protected function replaceIds(array $row, array $replacements): array
    {
        return array_map(
            fn (mixed $value): mixed => is_string($value) && (str_contains($value, 'usr_') || str_contains($value, 'wsp_')) ? strtr($value, $replacements) : $value,
            $row,
        );
    }

    /**
     * The table-specific changes to a row before it's inserted.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $copied
     * @return array<string, mixed>
     */
    protected function prepare(string $table, array $row, ZipArchive $zip, string $workspaceId, array &$copied): array
    {
        switch ($table) {
            case 'mailboxes':
                $hosted = $row['driver'] === 'hosted';

                return [
                    ...$row,
                    ...array_fill_keys(BuildExport::SECRET_COLUMNS['mailboxes'], null),
                    'polled_at' => null,
                    'imap_uid_validity' => null,
                    'imap_last_uid' => null,
                    'last_error' => $hosted ? $row['last_error'] : __('Imported without credentials. Edit the mailbox and enter them again to reconnect.'),
                ];
            case 'automations':
                return [...$row, 'webhook_token_hash' => null];
            case 'webhooks':
                return [...$row, 'secret' => Crypt::encryptString('whsec_'.Str::random(40)), 'is_enabled' => false, 'disabled_at' => now()];
            case 'ticket_events':
                return [...$row, 'automation_run_id' => null];
            case 'attachments':
                return $this->copyAttachmentFile($row, $zip, $workspaceId, $copied);
            default:
                return $row;
        }
    }

    /**
     * Put the attachment's file from the zip on the attachments disk in this workspace's folder and
     * point the row at it. A file missing from the zip stays missing, as it was in the export.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $copied
     * @return array<string, mixed>
     */
    protected function copyAttachmentFile(array $row, ZipArchive $zip, string $workspaceId, array &$copied): array
    {
        foreach (['id', 'ticket_id'] as $column) {
            if (! preg_match('/^[a-z0-9_]+$/', (string) $row[$column])) {
                throw new RuntimeException(__('The export is corrupt: an attachment has an invalid id.'));
            }
        }

        $name = basename((string) $row['filename']);
        $safeName = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', $name));
        $safeName = in_array($safeName, ['', '.', '..'], true) ? 'file' : $safeName;
        $disk = config('harbor.attachments.disk');
        $path = "{$workspaceId}/tickets/{$row['ticket_id']}/{$row['id']}/{$safeName}";
        $stream = $zip->getStream("attachments/{$row['id']}/{$name}");

        if ($stream !== false) {
            Storage::disk($disk)->writeStream($path, $stream);
            $copied[] = $path;
            fclose($stream);
        }

        return [...$row, 'disk' => $disk, 'path' => $path];
    }

    /**
     * Events are inserted before the automation runs they belong to, so the link is set afterwards.
     */
    protected function linkEventsToRuns(ZipArchive $zip): void
    {
        foreach ($this->rows($zip, 'ticket_events') as $row) {
            if ($row['automation_run_id'] !== null) {
                DB::table('ticket_events')->where('id', $row['id'])->update(['automation_run_id' => $row['automation_run_id']]);
            }
        }
    }

    /**
     * Fail when a crafted export points a row at another workspace's data: every foreign key to a
     * tenant table, and every morph pair whose target is a tenant model, must not reference a row
     * of a different workspace. Users are global, and dangling polymorphic ids are allowed.
     */
    protected function assertReferencesStayInWorkspace(string $workspaceId): void
    {
        foreach (array_keys(BuildExport::WORKSPACE_TABLES) as $table) {
            if (in_array($table, ['workspaces', 'users'], true)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $referenced = $foreignKey['foreign_table'];
                $column = $foreignKey['columns'][0];

                if (! Schema::hasColumn($referenced, 'workspace_id')) {
                    continue;
                }

                $crosses = DB::table($table)
                    ->join($referenced, "$referenced.{$foreignKey['foreign_columns'][0]}", '=', "$table.$column")
                    ->where("$table.workspace_id", $workspaceId)
                    ->where("$referenced.workspace_id", '!=', $workspaceId)
                    ->exists();

                $this->failIf($crosses, $table, $column);
            }

            $columns = Schema::getColumnListing($table);

            foreach ($columns as $typeColumn) {
                if (! str_ends_with($typeColumn, '_type')) {
                    continue;
                }

                $idColumn = substr($typeColumn, 0, -5).'_id';

                if (! in_array($idColumn, $columns, true)) {
                    continue;
                }

                foreach (DB::table($table)->where('workspace_id', $workspaceId)->whereNotNull($typeColumn)->distinct()->pluck($typeColumn) as $alias) {
                    $model = Relation::getMorphedModel($alias);

                    if ($model === null) {
                        throw new RuntimeException(__('The export is invalid: :table.:column has an unknown type.', ['table' => $table, 'column' => $typeColumn]));
                    }

                    $target = (new $model)->getTable();

                    if ($target === 'users') {
                        continue;
                    }

                    $foreign = $target === 'workspaces' ? 'id' : 'workspace_id';
                    $crosses = DB::table($table)
                        ->join($target, "$target.id", '=', "$table.$idColumn")
                        ->where("$table.workspace_id", $workspaceId)
                        ->where("$table.$typeColumn", $alias)
                        ->where("$target.$foreign", '!=', $workspaceId)
                        ->exists();

                    $this->failIf($crosses, $table, $idColumn);
                }
            }
        }
    }

    protected function failIf(bool $condition, string $table, string $column): void
    {
        if ($condition) {
            throw new RuntimeException(__('The export is invalid: :table.:column points at data outside this workspace.', ['table' => $table, 'column' => $column]));
        }
    }

    /**
     * Take the exported workspace's settings, and make sure its ticket numbers continue after the
     * highest one. Never its name, subdomain or id.
     *
     * @param  array<string, string>  $replacements
     */
    protected function applyWorkspace(ZipArchive $zip, string $workspaceId, array $replacements): void
    {
        $workspaceRows = $this->rows($zip, 'workspaces');
        $exported = $workspaceRows->valid() ? $workspaceRows->current() : [];
        $settings = is_string($exported['settings'] ?? null) ? strtr($exported['settings'], $replacements) : null;
        $highest = (int) DB::table('tickets')->where('workspace_id', $workspaceId)->max('number');

        DB::table('workspaces')->where('id', $workspaceId)->update([
            ...(is_array(json_decode((string) $settings, true)) ? ['settings' => $settings] : []),
            'next_ticket_number' => max((int) ($exported['next_ticket_number'] ?? 1), $highest + 1),
        ]);
    }

    /**
     * The rows of a table's JSON Lines file, one line at a time.
     *
     * @return Generator<int, array<string, mixed>>
     */
    protected function rows(ZipArchive $zip, string $table): Generator
    {
        $stream = $zip->getStream("$table.jsonl");

        if ($stream === false) {
            throw new RuntimeException(__('The export is incomplete.'));
        }

        try {
            while (($line = fgets($stream)) !== false) {
                if (trim($line) !== '') {
                    yield json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                }
            }
        } finally {
            fclose($stream);
        }
    }
}
