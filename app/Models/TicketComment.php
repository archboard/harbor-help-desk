<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\TicketCommentFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A message in a ticket's conversation: a public reply, or an internal note only agents see.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property string $author_type
 * @property string $author_id
 * @property string $body
 * @property bool $is_internal
 * @property CarbonImmutable|null $edited_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Model $author
 * @property-read Ticket|null $ticket
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\TicketCommentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereAuthorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereEditedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereIsInternal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereWorkspaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment withoutTrashed()
 *
 * @property-read Collection<int, Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property string|null $message_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketComment whereMessageId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class TicketComment extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<TicketCommentFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids, SoftDeletes;

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
            'is_internal' => 'boolean',
            'edited_at' => 'datetime',
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
     * The user or contact who wrote the comment.
     *
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'cmt_';
    }

    public function isWrittenBy(User|Contact $author): bool
    {
        return $this->author_type === $author->getMorphClass() && $this->author_id === $author->getKey();
    }
}
