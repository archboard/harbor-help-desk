<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\TicketFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @mixin \Eloquent
 */
#[Fillable(['desk_id', 'requester_id', 'subject', 'status', 'priority', 'due_at'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => TicketStatus::OPEN->value,
        'priority' => TicketPriority::NORMAL->value,
    ];

    public function idPrefix(): string
    {
        return 'tkt_';
    }

    /**
     * Number the ticket from its workspace's counter, e.g. #1042.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->number ??= Workspace::allocateTicketNumber($ticket->workspace_id);
        });
    }

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

    /**
     * @return BelongsTo<Desk, $this>
     */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class)->withTrashed();
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'due_at' => 'datetime',
        ];
    }
}
