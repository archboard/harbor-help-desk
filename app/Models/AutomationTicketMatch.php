<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remembers when a scheduled automation last fired for a ticket, so a sweep fires it once per
 * ticket, or again once `refire_after_days` have passed.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $automation_id
 * @property string $ticket_id
 * @property CarbonImmutable $fired_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Automation $automation
 * @property-read Ticket|null $ticket
 * @property-read Workspace $workspace
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereAutomationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereFiredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AutomationTicketMatch whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class AutomationTicketMatch extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    use BelongsToWorkspace, HasScru128Ids;

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
            'fired_at' => 'immutable_datetime',
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
        return $this->belongsTo(Ticket::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'atm_';
    }
}
