<?php

namespace App\Models;

use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * One entry in a ticket's audit trail. Events are append-only and stored as structured
 * data, then rendered into a sentence when read, e.g. "Jane Doe changed the priority to High."
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property TicketEventType $type
 * @property array<array-key, mixed> $properties
 * @property CarbonImmutable $created_at
 * @property-read Model|null $actor
 * @property-read Ticket|null $ticket
 * @property-read Workspace $workspace
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereActorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketEvent whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class TicketEvent extends Model
{
    use BelongsToWorkspace, HasScru128Ids;

    public const null UPDATED_AT = null;

    public function idPrefix(): string
    {
        return 'tev_';
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ticket events cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Ticket events cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    /**
     * The user or contact who caused the event. Null for events Harbor caused itself.
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The event as a sentence for people to read. Times use `$timezone` when given (queued
     * notifications have no signed-in viewer), else the signed-in user's.
     */
    public function description(?string $timezone = null): string
    {
        $properties = $this->properties;
        $replace = ['actor' => $this->actorName()];

        return match ($this->type) {
            TicketEventType::CREATED => __(':actor created the ticket.', $replace),
            TicketEventType::SUBJECT_CHANGED => __(':actor changed the subject to “:to”.', $replace + ['to' => $properties['to']]),
            TicketEventType::STATUS_CHANGED => __(':actor changed the status to :to.', $replace + ['to' => TicketStatus::from($properties['to'])->label()]),
            TicketEventType::PRIORITY_CHANGED => __(':actor changed the priority to :to.', $replace + ['to' => TicketPriority::from($properties['to'])->label()]),
            TicketEventType::DUE_DATE_CHANGED => $properties['to'] === null
                ? __(':actor removed the due date.', $replace)
                : __(':actor set the due date to :to.', $replace + ['to' => $this->localTime($properties['to'], $timezone)]),
            TicketEventType::DESK_CHANGED => __(':actor moved the ticket to :to.', $replace + ['to' => $properties['to']]),
            TicketEventType::CUSTOM_FIELD_CHANGED => $properties['to'] === null
                ? __(':actor cleared :name.', $replace + ['name' => $properties['name']])
                : __(':actor set :name to :to.', $replace + ['name' => $properties['name'], 'to' => $this->customFieldValue($properties['to'])]),
            TicketEventType::ASSIGNEE_ADDED => __(':actor assigned :name.', $replace + ['name' => $properties['name']]),
            TicketEventType::ASSIGNEE_REMOVED => __(':actor unassigned :name.', $replace + ['name' => $properties['name']]),
            TicketEventType::TAG_ADDED => __(':actor added the tag “:name”.', $replace + ['name' => $properties['name']]),
            TicketEventType::TAG_REMOVED => __(':actor removed the tag “:name”.', $replace + ['name' => $properties['name']]),
            TicketEventType::COMMENT_ADDED => __(':actor replied.', $replace),
            TicketEventType::INTERNAL_NOTE_ADDED => __(':actor added an internal note.', $replace),
            TicketEventType::COMMENT_EDITED => __(':actor edited a comment.', $replace),
            TicketEventType::COMMENT_DELETED => __(':actor deleted a comment.', $replace),
            TicketEventType::ATTACHMENT_ADDED => __(':actor attached :filename.', $replace + ['filename' => $properties['filename']]),
            TicketEventType::ATTACHMENT_REMOVED => __(':actor removed the attachment :filename.', $replace + ['filename' => $properties['filename']]),
            TicketEventType::REMINDER_SET => __(':actor set a reminder for :at.', $replace + ['at' => $this->localTime($properties['remind_at'], $timezone)]),
            TicketEventType::EMAIL_BOUNCED => __('An emailed reply bounced: :reason', ['reason' => $properties['reason']]),
            TicketEventType::DELETED => __(':actor deleted the ticket.', $replace),
            TicketEventType::RESTORED => __(':actor restored the ticket.', $replace),
        };
    }

    /**
     * A stored time in the viewing user's timezone. Descriptions are built per request, so
     * each viewer reads their own local time.
     */
    protected function localTime(string $time, ?string $timezone = null): string
    {
        return CarbonImmutable::parse($time)
            ->setTimezone($timezone ?: Auth::user()?->getAttribute('timezone') ?: config('app.timezone'))
            ->toDayDateTimeString();
    }

    /**
     * A custom field value as text: Yes or No for a checkbox, a comma-separated list for a multi-select.
     */
    protected function customFieldValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? __('Yes') : __('No'),
            is_array($value) => implode(', ', $value),
            default => (string) $value,
        };
    }

    public function actorName(): string
    {
        if ($this->actor_type === null) {
            return __('Harbor');
        }

        return $this->actor?->getAttribute('name') ?? __('A deleted user');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketEventType::class,
            'properties' => 'array',
        ];
    }
}
