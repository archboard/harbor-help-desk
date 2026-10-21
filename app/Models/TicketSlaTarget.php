<?php

namespace App\Models;

use App\Enums\SlaMetric;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SLA clock on a ticket: its first response or its resolution.
 *
 * `target_minutes` are business minutes of the policy's schedule (plain minutes when 24/7). Time
 * spent paused is added to the due time as `paused_business_minutes`. `warn_at` and `due_at` are
 * derived and stored so the checker can find them through an index; they're null while paused.
 * A breach is final: `breached_at` is never cleared.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property SlaMetric $metric
 * @property int $target_minutes
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $paused_at
 * @property int $paused_business_minutes
 * @property CarbonImmutable|null $warn_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $warned_at
 * @property CarbonImmutable|null $breached_at
 * @property CarbonImmutable|null $achieved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Ticket $ticket
 * @property-read Workspace $workspace
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereAchievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereBreachedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereDueAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereMetric($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget wherePausedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget wherePausedBusinessMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereTargetMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereWarnAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereWarnedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketSlaTarget whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class TicketSlaTarget extends Model
{
    use BelongsToWorkspace, HasScru128Ids;

    public function idPrefix(): string
    {
        return 'slt_';
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Derive `warn_at` and `due_at` from the start, the target and the paused time. Doesn't save.
     */
    public function recalculate(?BusinessSchedule $schedule, int $warningPercent): void
    {
        if ($this->paused_at !== null) {
            $this->warn_at = $this->due_at = null;

            return;
        }

        $this->due_at = BusinessSchedule::addMinutesFor($schedule, $this->started_at, $this->target_minutes + $this->paused_business_minutes);
        $this->warn_at = BusinessSchedule::addMinutesFor($schedule, $this->started_at, intdiv($this->target_minutes * $warningPercent, 100) + $this->paused_business_minutes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric' => SlaMetric::class,
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'warn_at' => 'datetime',
            'due_at' => 'datetime',
            'warned_at' => 'datetime',
            'breached_at' => 'datetime',
            'achieved_at' => 'datetime',
        ];
    }
}
