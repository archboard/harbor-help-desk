<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\AttachmentFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file on a ticket, optionally attached to one of its comments. The file lives on the
 * configured attachments disk; this row holds its metadata and SHA-256 checksum.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property string|null $ticket_comment_id
 * @property string $uploader_type
 * @property string $uploader_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 * @property string $checksum
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read TicketComment|null $comment
 * @property-read Ticket|null $ticket
 * @property-read Model $uploader
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\AttachmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereMimeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereSize($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereTicketCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUploaderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereUploaderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Attachment whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Attachment extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<AttachmentFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<TicketComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'ticket_comment_id')->withTrashed();
    }

    /**
     * The user or contact who uploaded the file.
     *
     * @return MorphTo<Model, $this>
     */
    public function uploader(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'att_';
    }

    public function isUploadedBy(User|Contact $uploader): bool
    {
        return $this->uploader_type === $uploader->getMorphClass() && $this->uploader_id === $uploader->getKey();
    }

    /**
     * Stream the file to the browser under its original name.
     */
    public function download(): StreamedResponse
    {
        return Storage::disk($this->disk)->download($this->path, $this->filename);
    }
}
