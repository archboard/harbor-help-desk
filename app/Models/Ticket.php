<?php

namespace App\Models;

use App\Automations\RunCausation;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Events\TicketActivityRecorded;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A request for help from a contact, worked on a desk by its assignees.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $desk_id
 * @property string $requester_id
 * @property int $number
 * @property string $subject
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, User> $assignees
 * @property-read int|null $assignees_count
 * @property-read Desk|null $desk
 * @property-read Contact $requester
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\TicketFactory factory($count = null, $state = [])
 * @method static Builder<static>|Ticket newModelQuery()
 * @method static Builder<static>|Ticket newQuery()
 * @method static Builder<static>|Ticket onlyTrashed()
 * @method static Builder<static>|Ticket query()
 * @method static Builder<static>|Ticket visibleTo(\App\Models\User $user)
 * @method static Builder<static>|Ticket whereCreatedAt($value)
 * @method static Builder<static>|Ticket whereDeletedAt($value)
 * @method static Builder<static>|Ticket whereDeskId($value)
 * @method static Builder<static>|Ticket whereDueAt($value)
 * @method static Builder<static>|Ticket whereId($value)
 * @method static Builder<static>|Ticket whereNumber($value)
 * @method static Builder<static>|Ticket wherePriority($value)
 * @method static Builder<static>|Ticket whereRequesterId($value)
 * @method static Builder<static>|Ticket whereStatus($value)
 * @method static Builder<static>|Ticket whereSubject($value)
 * @method static Builder<static>|Ticket whereUpdatedAt($value)
 * @method static Builder<static>|Ticket whereWorkspaceId($value)
 * @method static Builder<static>|Ticket withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Ticket withoutTrashed()
 *
 * @property-read Collection<int, TicketEvent> $events
 * @property-read int|null $events_count
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read Collection<int, TicketComment> $comments
 * @property-read int|null $comments_count
 * @property-read Collection<int, Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read Collection<int, TicketReminder> $reminders
 * @property-read int|null $reminders_count
 * @property array<string, mixed> $custom_fields
 *
 * @method static Builder<static>|Ticket whereCustomFields($value)
 *
 * @property string|null $mailbox_id
 * @property-read Mailbox|null $mailbox
 *
 * @method static Builder<static>|Ticket whereMailboxId($value)
 *
 * @property-read Collection<int, TicketSlaTarget> $slaTargets
 * @property-read int|null $sla_targets_count
 * @property TicketSource $source
 * @property-read SlaPolicy|null $slaPolicy
 * @property string|null $sla_policy_id
 *
 * @method static Builder<static>|Ticket whereSlaPolicyId($value)
 * @method static Builder<static>|Ticket whereSource($value)
 *
 * @property CarbonImmutable|null $survey_sent_at
 * @property-read SatisfactionRating|null $satisfactionRating
 *
 * @method static Builder<static>|Ticket whereSurveySentAt($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Ticket extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<TicketFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TicketStatus::OPEN->value,
        'priority' => TicketPriority::NORMAL->value,
        'source' => TicketSource::WEB->value,
        'custom_fields' => '{}',
    ];

    /*
    |--------------------------------------------------------------------------
    | Booting
    |--------------------------------------------------------------------------
    */

    /**
     * Number the ticket from its workspace's counter, e.g. #1042.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->number ??= Workspace::allocateTicketNumber($ticket->workspace_id);
        });
    }

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
            'number' => 'integer',
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'source' => TicketSource::class,
            'due_at' => 'datetime',
            'survey_sent_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Custom field values keyed by field id. Stored as a JSON object even when empty, which
     * the plain `array` cast would write as `[]`.
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function customFields(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => json_decode($value ?? '{}', true),
            set: fn (array $value): string => json_encode((object) $value, JSON_THROW_ON_ERROR),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Limit to tickets on desks the user can see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->whereHas('desk', fn (Builder $query) => $query->visibleTo($user));
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Desk, $this>
     */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class)->withTrashed();
    }

    /**
     * The mailbox the ticket's email thread lives in.
     *
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * The ticket's own SLA policy, which overrides its desk's.
     *
     * @return BelongsTo<SlaPolicy, $this>
     */
    public function slaPolicy(): BelongsTo
    {
        return $this->belongsTo(SlaPolicy::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The agents working this ticket.
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_user')->withTimestamps();
    }

    /**
     * The conversation, oldest first.
     *
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest()->orderBy('id');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->oldest()->orderBy('id');
    }

    /**
     * @return HasMany<TicketReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(TicketReminder::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /**
     * @return HasMany<TicketSlaTarget, $this>
     */
    public function slaTargets(): HasMany
    {
        return $this->hasMany(TicketSlaTarget::class);
    }

    /**
     * The requester's rating of how the ticket was handled.
     *
     * @return HasOne<SatisfactionRating, $this>
     */
    public function satisfactionRating(): HasOne
    {
        return $this->hasOne(SatisfactionRating::class);
    }

    /**
     * The audit trail, oldest first.
     *
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->oldest()->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'tkt_';
    }

    /**
     * The policy that sets the ticket's SLA targets: its own, else its desk's.
     */
    public function effectiveSlaPolicy(): ?SlaPolicy
    {
        return $this->slaPolicy ?? $this->desk->slaPolicy;
    }

    /**
     * Append an entry to the audit trail. A null actor means Harbor did it.
     *
     * @param  array<string, mixed>  $properties
     */
    public function recordEvent(TicketEventType $type, User|Contact|Automation|null $actor, array $properties = []): TicketEvent
    {
        $event = new TicketEvent(['type' => $type, 'properties' => $properties, 'automation_run_id' => app(RunCausation::class)->runId]);
        $event->actor()->associate($actor);
        $event->ticket()->associate($this)->saveOrFail();

        TicketActivityRecorded::dispatch($event);

        return $event;
    }
}
