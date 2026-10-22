<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\TicketReminderFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reminder about a ticket. A personal reminder notifies its user; a ticket-level reminder
 * (no user) notifies whoever is assigned when it comes due.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property string|null $user_id
 * @property string|null $created_by
 * @property CarbonImmutable $remind_at
 * @property string|null $note
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $creator
 * @property-read Ticket|null $ticket
 * @property-read User|null $user
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\TicketReminderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereRemindAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketReminder whereWorkspaceId($value)
 * @method static Builder<static>|TicketReminder visibleTo(\App\Models\User $user)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class TicketReminder extends Model
{
    /** @use HasFactory<TicketReminderFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    public function idPrefix(): string
    {
        return 'rmd_';
    }

    /**
     * Personal reminders are visible only to their user; ticket-level ones to everyone on the ticket.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', $user->getKey()));
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The user a personal reminder is for. Null for ticket-level reminders.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isTicketLevel(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Who gets notified now: the reminder's user, or the ticket's current assignees.
     *
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        if ($this->isTicketLevel()) {
            return $this->ticket->assignees;
        }

        return Collection::wrap($this->user);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }
}
