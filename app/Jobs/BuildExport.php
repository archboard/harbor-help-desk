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
            ExportType::WORKSPACE => throw new \LogicException('Workspace exports are not built yet.'),
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

        $this->addJsonLines($zip, 'contact.jsonl', [$contact]);
        $this->addJsonLines($zip, 'tickets.jsonl', $tickets->lazyById());
        $this->addJsonLines($zip, 'comments.jsonl', TicketComment::query()->whereIn('ticket_id', $ticketIds)->where('is_internal', false)->orderBy('id')->lazyById());
        $this->addJsonLines($zip, 'ratings.jsonl', SatisfactionRating::query()->where('contact_id', $contact->getKey())->orderBy('id')->lazyById());

        $attachments = Attachment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->where(fn ($query) => $query->whereNull('ticket_comment_id')->orWhereIn('ticket_comment_id', TicketComment::query()->select('id')->where('is_internal', false)))
            ->orderBy('id');

        $this->addJsonLines($zip, 'attachments.jsonl', $attachments->clone()->lazyById());

        foreach ($attachments->lazyById() as $attachment) {
            $stream = Storage::disk($attachment->disk)->readStream($attachment->path);

            if ($stream === null) {
                continue;
            }

            $tempFile = $this->tempFile();
            $tempFiles[] = $tempFile;
            file_put_contents($tempFile, $stream);
            fclose($stream);

            $zip->addFile($tempFile, "attachments/{$attachment->getKey()}/".basename($attachment->filename));
        }
    }

    protected function tempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'export') ?: throw new \RuntimeException('Could not create a temporary file.');
    }

    /**
     * @param  iterable<int, Model>  $models
     */
    protected function addJsonLines(ZipArchive $zip, string $name, iterable $models): void
    {
        $lines = '';

        foreach ($models as $model) {
            $lines .= json_encode($model->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        }

        $zip->addFromString($name, $lines);
    }
}
