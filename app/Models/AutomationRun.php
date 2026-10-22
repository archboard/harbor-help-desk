<?php

namespace App\Models;

use App\Data\Automations\AutomationDefinitionData;
use App\Enums\AutomationRunStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One evaluation of an automation for a ticket event. It keeps a snapshot of the definition it
 * ran, so editing the automation never changes a run in progress, and a log with one entry per
 * step. `state` holds where a waiting run resumes. Runs are pruned after 30 days.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $automation_id
 * @property string|null $ticket_id
 * @property string|null $ticket_event_id
 * @property string|null $parent_run_id
 * @property int $depth
 * @property AutomationRunStatus $status
 * @property AutomationDefinitionData $definition
 * @property array<array-key, mixed> $log
 * @property array<array-key, mixed> $state
 * @property CarbonImmutable|null $resume_at
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Automation $automation
 * @property-read Ticket|null $ticket
 * @property-read AutomationRun|null $parentRun
 *
 * @method static Builder<static>|AutomationRun newModelQuery()
 * @method static Builder<static>|AutomationRun newQuery()
 * @method static Builder<static>|AutomationRun query()
 * @method static Builder<static>|AutomationRun whereAutomationId($value)
 * @method static Builder<static>|AutomationRun whereCreatedAt($value)
 * @method static Builder<static>|AutomationRun whereDefinition($value)
 * @method static Builder<static>|AutomationRun whereDepth($value)
 * @method static Builder<static>|AutomationRun whereError($value)
 * @method static Builder<static>|AutomationRun whereId($value)
 * @method static Builder<static>|AutomationRun whereLog($value)
 * @method static Builder<static>|AutomationRun whereParentRunId($value)
 * @method static Builder<static>|AutomationRun whereResumeAt($value)
 * @method static Builder<static>|AutomationRun whereState($value)
 * @method static Builder<static>|AutomationRun whereStatus($value)
 * @method static Builder<static>|AutomationRun whereTicketEventId($value)
 * @method static Builder<static>|AutomationRun whereTicketId($value)
 * @method static Builder<static>|AutomationRun whereUpdatedAt($value)
 * @method static Builder<static>|AutomationRun whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class AutomationRun extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    use BelongsToWorkspace, HasScru128Ids, MassPrunable;

    /** How long runs are kept. */
    public const int RETENTION_DAYS = 30;

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
            'depth' => 'integer',
            'status' => AutomationRunStatus::class,
            'definition' => AutomationDefinitionData::class,
            'log' => 'array',
            'state' => 'array',
            'resume_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class)->withTrashed();
    }

    /**
     * @return BelongsTo<AutomationRun, $this>
     */
    public function parentRun(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_run_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'arn_';
    }

    /**
     * Cancel the run if it is still waiting, and log why. A run claimed for resuming meanwhile is left alone.
     */
    public function cancelWaiting(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $run = static::query()->whereKey($this->getKey())->where('status', AutomationRunStatus::WAITING)->lockForUpdate()->first();

            $run?->forceFill([
                'status' => AutomationRunStatus::CANCELLED,
                'resume_at' => null,
                'log' => [...$run->log, ['type' => 'cancelled', 'reason' => $reason]],
            ])->save();
        });
    }

    /**
     * Old runs of every workspace. Landlord code, so the tenant scope is removed on purpose.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::withoutGlobalScope('workspace')->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
