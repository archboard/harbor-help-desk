<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class AddTicketAttachment
{
    /**
     * Store a file on the ticket, optionally as part of one of its comments.
     */
    public function handle(User $actor, Ticket $ticket, UploadedFile $file, ?TicketComment $comment = null): Attachment
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        Validator::make(['file' => $file], ['file' => self::rules()])->validate();

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
        return [
            'file',
            'max:'.config('harbor.attachments.max_size_kb'),
            function (string $attribute, UploadedFile $file, Closure $fail): void {
                $extensions = [strtolower($file->getClientOriginalExtension()), strtolower((string) $file->guessExtension())];

                if (array_intersect($extensions, config('harbor.attachments.blocked_extensions')) !== []) {
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
