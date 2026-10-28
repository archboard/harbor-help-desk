<?php

namespace App\Models;

use App\Actions\Tickets\RecomputeTicketMetrics;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\TicketMetricFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ticket's reporting numbers, derived from its comments and status changes by
 * {@see RecomputeTicketMetrics}. Kept off `tickets` so recomputing never
 * touches the ticket. The `*_business_minutes` use the ticket's effective schedule (calendar
 * minutes without one).
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property CarbonImmutable|null $first_responded_at
 * @property string|null $first_responder_id
 * @property int|null $first_response_minutes
 * @property int|null $first_response_business_minutes
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $resolution_minutes
 * @property int|null $resolution_business_minutes
 * @property int $reopen_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $firstResponder
 * @property-read Ticket|null $ticket
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\TicketMetricFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereFirstRespondedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereFirstResponderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereFirstResponseBusinessMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereFirstResponseMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereReopenCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereResolutionBusinessMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereResolutionMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereResolvedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TicketMetric whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class TicketMetric extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<TicketMetricFactory> */
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
            'first_responded_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function firstResponder(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'tmt_';
    }
}
