<?php

namespace App\Models;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Jobs\BuildExport;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * A zip of data that a workspace admin asked for, built by {@see BuildExport} on the
 * attachments disk. For a contact export `subject_id` is the contact. Exports expire after
 * seven days and are then deleted along with their file.
 *
 * @property string $id
 * @property string $workspace_id
 * @property ExportType $type
 * @property string|null $subject_id
 * @property string|null $requested_by
 * @property ExportStatus $status
 * @property string|null $path
 * @property int|null $size
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Contact|null $contact
 * @property-read User|null $requester
 * @property-read Workspace $workspace
 *
 * @method static Builder<static>|Export newModelQuery()
 * @method static Builder<static>|Export newQuery()
 * @method static Builder<static>|Export query()
 * @method static Builder<static>|Export whereCompletedAt($value)
 * @method static Builder<static>|Export whereCreatedAt($value)
 * @method static Builder<static>|Export whereExpiresAt($value)
 * @method static Builder<static>|Export whereId($value)
 * @method static Builder<static>|Export wherePath($value)
 * @method static Builder<static>|Export whereRequestedBy($value)
 * @method static Builder<static>|Export whereSize($value)
 * @method static Builder<static>|Export whereStatus($value)
 * @method static Builder<static>|Export whereSubjectId($value)
 * @method static Builder<static>|Export whereType($value)
 * @method static Builder<static>|Export whereUpdatedAt($value)
 * @method static Builder<static>|Export whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Export extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    use BelongsToWorkspace, HasScru128Ids, Prunable;

    public const int LIFETIME_DAYS = 7;

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
            'type' => ExportType::class,
            'status' => ExportStatus::class,
            'size' => 'integer',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The contact of a contact export, when it still exists.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'subject_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'exp_';
    }

    /**
     * Expired exports across all workspaces. Pruning runs with no current workspace, so the
     * tenant scope is removed here (landlord code).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope('workspace')->where('expires_at', '<', now());
    }

    protected function pruning(): void
    {
        if ($this->path !== null) {
            Storage::disk(config('harbor.attachments.disk'))->delete($this->path);
        }
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::COMPLETED && $this->path !== null && $this->expires_at->isFuture();
    }

    /**
     * A link to the browser download, signed until the export expires. Relative signature, so it
     * validates on whichever host the workspace is served from.
     */
    public function signedDownloadUrl(): string
    {
        $path = URL::temporarySignedRoute('exports.download', $this->expires_at, ['export' => $this->getKey()], absolute: false);

        return $this->workspace->url($path);
    }
}
