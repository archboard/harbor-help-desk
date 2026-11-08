<?php

namespace App\Models;

use App\Enums\TicketEventType;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\WebhookFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An endpoint that gets a signed POST for the ticket events it subscribes to. An empty
 * `event_types` means every event. The `secret` signs the requests and is encrypted at rest;
 * it only leaves the app when it's created or rotated. After
 * {@see self::MAX_CONSECUTIVE_FAILURES} failed deliveries in a row the webhook disables itself.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $url
 * @property string|null $description
 * @property array<array-key, mixed> $event_types
 * @property string $secret
 * @property bool $is_enabled
 * @property int $consecutive_failures
 * @property CarbonImmutable|null $disabled_at
 * @property string|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, WebhookDelivery> $deliveries
 * @property-read int|null $deliveries_count
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\WebhookFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereConsecutiveFailures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereDisabledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereEventTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Webhook whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Webhook extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WebhookFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    public const int MAX_CONSECUTIVE_FAILURES = 20;

    /** @var list<string> */
    protected $hidden = ['secret'];

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
            'event_types' => 'array',
            'secret' => 'encrypted',
            'is_enabled' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'immutable_datetime',
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'whk_';
    }

    public function subscribesTo(TicketEventType $type): bool
    {
        return $this->event_types === [] || in_array($type->value, $this->event_types, true);
    }
}
