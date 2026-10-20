<?php

namespace App\Actions\Tickets;

use App\Data\Tickets\AddTicketAttachmentData;
use App\Data\Workspaces\WorkspaceSettings;
use App\Enums\TicketEventType;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AddTicketAttachment
{
    /**
     * Store the uploaded `file` on the ticket, optionally as part of one of its comments. A contact
     * (sending a file by email) skips the policy check.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User|Contact $actor, Ticket $ticket, array $input, ?TicketComment $comment = null): Attachment
    {
        if ($actor instanceof User) {
            Gate::forUser($actor)->authorize('update', $ticket);
        }

        $file = AddTicketAttachmentData::validateAndCreate($input)->file;

        if ($comment !== null && $comment->ticket_id !== $ticket->getKey()) {
            throw ValidationException::withMessages(['comment' => __('The comment belongs to another ticket.')]);
        }

        $attachment = new Attachment([
            'disk' => config('harbor.attachments.disk'),
            'filename' => self::safeFilename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);
        $attachment->id = $attachment->newUniqueId();
        $attachment->path = "{$ticket->workspace_id}/tickets/{$ticket->getKey()}/{$attachment->id}/{$attachment->filename}";
        $attachment->ticket()->associate($ticket);
        $attachment->comment()->associate($comment);
        $attachment->uploader()->associate($actor);

        Storage::disk($attachment->disk)->putFileAs(dirname($attachment->path), $file, $attachment->filename);

        try {
            DB::transaction(function () use ($actor, $ticket, $attachment): void {
                $attachment->save();
                $ticket->recordEvent(TicketEventType::ATTACHMENT_ADDED, $actor, ['attachment_id' => $attachment->id, 'filename' => $attachment->filename]);
            });
        } catch (Throwable $exception) {
            Storage::disk($attachment->disk)->delete($attachment->path);

            throw $exception;
        }

        return $attachment;
    }

    /**
     * Validation rules for one uploaded file, also used for `attachments.*` on comments.
     *
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        // ponytail: falls back to the defaults where no workspace is current.
        $settings = Workspace::current()?->settings ?? new WorkspaceSettings;
        $blocked = $settings->effectiveBlockedExtensions();

        return [
            'file',
            'max:'.$settings->effectiveAttachmentMaxKb(),
            function (string $attribute, UploadedFile $file, Closure $fail) use ($blocked): void {
                $extensions = [strtolower($file->getClientOriginalExtension()), strtolower((string) $file->guessExtension())];

                if (array_intersect($extensions, $blocked) !== []) {
                    $fail(__('Files of this type can’t be attached.'));
                }
            },
        ];
    }

    /**
     * The client's filename without any path or control characters.
     */
    protected static function safeFilename(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', basename(str_replace('\\', '/', $name))));

        return $name === '' || $name === '.' || $name === '..' ? 'file' : mb_substr($name, -200);
    }
}
