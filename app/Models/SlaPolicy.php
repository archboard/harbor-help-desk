<?php

namespace App\Models;

use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\SlaPolicyFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How fast tickets on a desk must get a first response and a resolution, per priority.
 *
 * `targets` maps a priority to its metrics in minutes, e.g. `{"urgent": {"first_response": 60,
 * "resolution": 480}}`. A priority or metric that's left out isn't tracked. Minutes are business
 * minutes of the `businessSchedule`, or plain minutes when it's null (24/7). `warning_percent`
 * is how much of a target has elapsed when a ticket is flagged as at risk.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $business_schedule_id
 * @property string $name
 * @property int $warning_percent
 * @property array<array-key, mixed> $targets
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read BusinessSchedule|null $businessSchedule
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\SlaPolicyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereBusinessScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereTargets($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereWarningPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlaPolicy whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class SlaPolicy extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<SlaPolicyFactory> */
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
            'targets' => 'array',
            'warning_percent' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<BusinessSchedule, $this>
     */
    public function businessSchedule(): BelongsTo
    {
        return $this->belongsTo(BusinessSchedule::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'slp_';
    }

    /**
     * The target in minutes, or null when the policy doesn't track it.
     */
    public function targetFor(TicketPriority $priority, SlaMetric $metric): ?int
    {
        return $this->targets[$priority->value][$metric->value] ?? null;
    }
}
