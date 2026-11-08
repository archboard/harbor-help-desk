<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\WebhookDeliveryFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event sent to one webhook. `event_type` is a ticket event type, or `ping` for a test
 * delivery, which has no ticket event. Kept for {@see self::RETENTION_DAYS} days.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $webhook_id
 * @property string|null $ticket_event_id
 * @property string $event_type
 * @property WebhookDeliveryStatus $status
 * @property int|null $response_status
 * @property int $attempts
 * @property string|null $error
 * @property int|null $duration_ms
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable $created_at
 * @property-read TicketEvent|null $ticketEvent
 * @property-read Webhook $webhook
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\WebhookDeliveryFactory factory($count = null, $state = [])
 * @method static Builder<static>|WebhookDelivery newModelQuery()
 * @method static Builder<static>|WebhookDelivery newQuery()
 * @method static Builder<static>|WebhookDelivery query()
 * @method static Builder<static>|WebhookDelivery whereAttempts($value)
 * @method static Builder<static>|WebhookDelivery whereCreatedAt($value)
 * @method static Builder<static>|WebhookDelivery whereDeliveredAt($value)
 * @method static Builder<static>|WebhookDelivery whereDurationMs($value)
 * @method static Builder<static>|WebhookDelivery whereError($value)
 * @method static Builder<static>|WebhookDelivery whereEventType($value)
 * @method static Builder<static>|WebhookDelivery whereId($value)
 * @method static Builder<static>|WebhookDelivery whereResponseStatus($value)
 * @method static Builder<static>|WebhookDelivery whereStatus($value)
 * @method static Builder<static>|WebhookDelivery whereTicketEventId($value)
 * @method static Builder<static>|WebhookDelivery whereWebhookId($value)
 * @method static Builder<static>|WebhookDelivery whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class WebhookDelivery extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WebhookDeliveryFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids, MassPrunable;

    public const string PING = 'ping';

    public const int RETENTION_DAYS = 30;

    public const UPDATED_AT = null;

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => WebhookDeliveryStatus::class,
            'response_status' => 'integer',
            'attempts' => 'integer',
            'duration_ms' => 'integer',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * @return BelongsTo<TicketEvent, $this>
     */
    public function ticketEvent(): BelongsTo
    {
        return $this->belongsTo(TicketEvent::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'whd_';
    }

    public function isPing(): bool
    {
        return $this->event_type === self::PING;
    }

    /**
     * Old deliveries across all workspaces. Pruning runs with no current workspace, so the
     * tenant scope is removed here (landlord code).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope('workspace')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
