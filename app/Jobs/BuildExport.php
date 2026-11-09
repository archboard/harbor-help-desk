<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Export;
use App\Models\SatisfactionRating;
use App\Models\TicketComment;
use App\Notifications\ExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Builds an export's zip in the export's workspace (tenant-aware by the multitenancy default):
 * JSON Lines files per table plus the attachment files, put on the attachments disk under
 * `{workspace_id}/exports/`, then tells the requester. Each export type adds its entries in
 * {@see self::addEntries()}.
 */
class BuildExport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Export $export) {}

    public function handle(): void
    {
        $zipPath = $this->tempFile();
        $tempFiles = [];
        $zip = new ZipArchive;

        try {
            $zip->open($zipPath, ZipArchive::OVERWRITE);
            $this->addEntries($zip, $tempFiles);
            $zip->close();

            $disk = Storage::disk(config('harbor.attachments.disk'));
            $path = $disk->putFileAs("{$this->export->workspace_id}/exports", new File($zipPath), "{$this->export->getKey()}.zip");
            $size = filesize($zipPath);
        } finally {
            foreach ([$zipPath, ...$tempFiles] as $file) {
                @unlink($file);
            }
        }

        $this->export->update([
            'status' => ExportStatus::COMPLETED,
            'path' => $path,
            'size' => $size,
            'completed_at' => now(),
        ]);

        $this->export->requester?->notify(new ExportReadyNotification($this->export));
    }

    public function failed(Throwable $exception): void
    {
        $this->export->update(['status' => ExportStatus::FAILED]);
    }

    /**
     * @param  list<string>  $tempFiles  Filled with the temporary files the zip reads from, for the caller to delete.
     */
    protected function addEntries(ZipArchive $zip, array &$tempFiles): void
    {
        match ($this->export->type) {
            ExportType::CONTACT => $this->addContactEntries($zip, $tempFiles),
            ExportType::WORKSPACE => $this->addWorkspaceEntries($zip, $tempFiles),
        };
    }

    /**
     * The contact, their tickets, every public comment on them, their ratings and the files
     * attached to those tickets or public comments. Internal notes and their files stay out.
     * Every query goes through the workspace scope, so another workspace's data can't appear.
     *
     * @param  list<string>  $tempFiles
     */
    protected function addContactEntries(ZipArchive $zip, array &$tempFiles): void
    {
        $contact = Contact::query()->findOrFail($this->export->subject_id);
        $tickets = $contact->tickets()->withTrashed()->orderBy('id');
        $ticketIds = $contact->tickets()->withTrashed()->pluck('id');

        $this->addJsonLines($zip, 'contact.jsonl', [$contact], $tempFiles);
        $this->addJsonLines($zip, 'tickets.jsonl', $tickets->lazyById(), $tempFiles);
        $this->addJsonLines($zip, 'comments.jsonl', TicketComment::query()->whereIn('ticket_id', $ticketIds)->where('is_internal', false)->orderBy('id')->lazyById(), $tempFiles);
        $this->addJsonLines($zip, 'ratings.jsonl', SatisfactionRating::query()->where('contact_id', $contact->getKey())->orderBy('id')->lazyById(), $tempFiles);

        $attachments = Attachment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where(fn ($query) => $query->whereNull('ticket_comment_id')->orWhereIn('ticket_comment_id', TicketComment::query()->select('id')->where('is_internal', false)))
            ->orderBy('id');

        $this->addJsonLines($zip, 'attachments.jsonl', $attachments->clone()->lazyById(), $tempFiles);

        $this->addAttachmentFiles($zip, $attachments->lazyById(), $tempFiles);
    }

    protected function tempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'export') ?: throw new \RuntimeException('Could not create a temporary file.');
    }

    /**
     * Every tenant table written in this order (parents first, so an import can insert in order).
     * Each entry is the table and the columns that order it uniquely: tables with an `id`
     * stream with `lazyById()`, pivots without one page through their key columns.
     * Anything with a `workspace_id` column must be listed here or in {@see self::EXCLUDED_WORKSPACE_TABLES}.
     */
    public const array WORKSPACE_TABLES = [
        'workspaces' => ['id'],
        'users' => ['id'],
        'workspace_user' => ['user_id'],
        'business_schedules' => ['id'],
        'sla_policies' => ['id'],
        'desks' => ['id'],
        'desk_user' => ['desk_id', 'user_id'],
        'custom_fields' => ['id'],
        'custom_field_desk' => ['custom_field_id', 'desk_id'],
        'tags' => ['id'],
        'contacts' => ['id'],
        'mailboxes' => ['id'],
        'automations' => ['id'],
        'macros' => ['id'],
        'saved_views' => ['id'],
        'tickets' => ['id'],
        'ticket_user' => ['ticket_id', 'user_id'],
        'tag_ticket' => ['ticket_id', 'tag_id'],
        'ticket_comments' => ['id'],
        'attachments' => ['id'],
        'ticket_events' => ['id'],
        'ticket_reminders' => ['id'],
        'satisfaction_ratings' => ['id'],
        'ticket_metrics' => ['id'],
        'ticket_sla_targets' => ['id'],
        'automation_runs' => ['id'],
        'automation_ticket_matches' => ['id'],
        'audit_events' => ['id'],
        'webhooks' => ['id'],
    ];

    /**
     * Tables with a `workspace_id` column that a workspace export leaves out on purpose:
     * exports are this feature's own bookkeeping, notifications are per-user and transient, and
     * webhook deliveries are a short-lived delivery log.
     */
    public const array EXCLUDED_WORKSPACE_TABLES = ['exports', 'notifications', 'webhook_deliveries'];

    /**
     * Columns written as null: mailbox credentials (`settings` holds the encrypted IMAP/SMTP/OAuth
     * secrets) and the push subscription, the automation webhook token hash, and the outgoing
     * webhook signing secret.
     */
    public const array SECRET_COLUMNS = [
        'mailboxes' => ['settings', 'push_secret', 'push_subscription_id', 'push_expires_at'],
        'automations' => ['webhook_token_hash'],
        'webhooks' => ['secret'],
    ];

    /**
     * Member columns that are safe to export. Never the password, remember token, two-factor
     * secrets or passkeys.
     */
    public const array USER_COLUMNS = ['id', 'name', 'email', 'email_verified_at', 'timezone', 'created_at', 'updated_at'];

    /**
     * Raw database rows of the workspace, one JSONL file per table (`{table}.jsonl`), every attachment
     * file, and a `manifest.json` with the row counts. Rows are the raw column values so an import can
     * insert them again with their ids. Queries use the query builder with an explicit `workspace_id`
     * filter, as the tenant scope doesn't apply to it.
     *
     * @param  list<string>  $tempFiles
     */
    protected function addWorkspaceEntries(ZipArchive $zip, array &$tempFiles): void
    {
        $workspaceId = $this->export->workspace_id;
        $counts = [];

        foreach (self::WORKSPACE_TABLES as $table => $keys) {
            $query = match ($table) {
                'workspaces' => DB::table($table)->where('id', $workspaceId),
                'users' => DB::table($table)->select(self::USER_COLUMNS)->whereIn('id', DB::table('workspace_user')->select('user_id')->where('workspace_id', $workspaceId)),
                default => DB::table($table)->where('workspace_id', $workspaceId),
            };

            $rows = $keys === ['id']
                ? $query->lazyById(1000)
                : $query->orderBy($keys[0])->when($keys[1] ?? null, fn ($q, string $secondKey) => $q->orderBy($secondKey))->lazy(1000);

            $secrets = array_flip(self::SECRET_COLUMNS[$table] ?? []);

            $counts[$table] = $this->addJsonLines($zip, "$table.jsonl", $rows, $tempFiles, $secrets);
        }

        $attachments = DB::table('attachments')->where('workspace_id', $workspaceId);
        $this->addAttachmentFiles($zip, $attachments->lazyById(1000), $tempFiles);

        $zip->addFromString('manifest.json', json_encode([
            'version' => 1,
            'workspace_id' => $workspaceId,
            'exported_at' => now()->toIso8601String(),
            'migration' => DB::table('migrations')->orderByDesc('id')->value('migration'),
            'tables' => $counts,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    /**
     * Copy each attachment's file into the zip under `attachments/{id}/{filename}`. Missing files are skipped.
     *
     * @param  iterable<int, Attachment|\stdClass>  $attachments
     * @param  list<string>  $tempFiles
     */
    protected function addAttachmentFiles(ZipArchive $zip, iterable $attachments, array &$tempFiles): void
    {
        foreach ($attachments as $attachment) {
            $stream = Storage::disk($attachment->disk)->readStream($attachment->path);

            if ($stream === null) {
                continue;
            }

            $tempFile = $this->tempFile();
            $tempFiles[] = $tempFile;
            file_put_contents($tempFile, $stream);
            fclose($stream);

            $zip->addFile($tempFile, "attachments/{$attachment->id}/".basename($attachment->filename));
        }
    }

    /**
     * Stream rows to a temporary file as JSON Lines and add it to the zip. Models are written with
     * `toArray()`, query-builder rows as their raw columns.
     *
     * @param  iterable<int, Model|object>  $rows
     * @param  list<string>  $tempFiles
     * @param  array<string, int>  $nullColumns  Column names (as keys) written as null.
     * @return int The number of rows written.
     */
    protected function addJsonLines(ZipArchive $zip, string $name, iterable $rows, array &$tempFiles, array $nullColumns = []): int
    {
        $tempFile = $this->tempFile();
        $tempFiles[] = $tempFile;
        $handle = fopen($tempFile, 'w') ?: throw new \RuntimeException('Could not open a temporary file.');
        $count = 0;

        foreach ($rows as $row) {
            $data = $row instanceof Model ? $row->toArray() : array_replace((array) $row, array_map(fn () => null, array_intersect_key($nullColumns, (array) $row)));

            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $count++;
        }

        fclose($handle);
        $zip->addFile($tempFile, $name);

        return $count;
    }
}
