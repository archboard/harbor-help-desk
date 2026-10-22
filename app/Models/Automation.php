<?php

namespace App\Models;

use App\Data\Automations\AutomationDefinitionData;
use App\Enums\DeskRole;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\AutomationFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A trigger and a list of steps that run on tickets as the automation itself, which shows
 * up as the actor in the ticket's trail. Workspace-wide, or limited to one desk when `desk_id` is set.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $desk_id
 * @property string $name
 * @property string|null $description
 * @property bool $enabled
 * @property int $position
 * @property string $trigger_type
 * @property AutomationDefinitionData $definition
 * @property int $schema_version
 * @property string|null $webhook_token_hash
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Desk|null $desk
 * @property-read User|null $createdBy
 * @property-read User|null $updatedBy
 *
 * @method static \Database\Factories\AutomationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Automation for(\App\Models\Ticket $ticket)
 * @method static Builder<static>|Automation newModelQuery()
 * @method static Builder<static>|Automation newQuery()
 * @method static Builder<static>|Automation query()
 * @method static Builder<static>|Automation whereCreatedAt($value)
 * @method static Builder<static>|Automation whereCreatedBy($value)
 * @method static Builder<static>|Automation whereDefinition($value)
 * @method static Builder<static>|Automation whereDescription($value)
 * @method static Builder<static>|Automation whereDeskId($value)
 * @method static Builder<static>|Automation whereEnabled($value)
 * @method static Builder<static>|Automation whereId($value)
 * @method static Builder<static>|Automation whereName($value)
 * @method static Builder<static>|Automation wherePosition($value)
 * @method static Builder<static>|Automation whereSchemaVersion($value)
 * @method static Builder<static>|Automation whereTriggerType($value)
 * @method static Builder<static>|Automation whereUpdatedAt($value)
 * @method static Builder<static>|Automation whereUpdatedBy($value)
 * @method static Builder<static>|Automation whereWebhookTokenHash($value)
 * @method static Builder<static>|Automation whereWorkspaceId($value)
 * @method static Builder<static>|Automation visibleTo(\App\Models\User $user)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Automation extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<AutomationFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    /** The shape version of the stored `definition`. */
    public const int SCHEMA_VERSION = 1;

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
            'enabled' => 'boolean',
            'position' => 'integer',
            'schema_version' => 'integer',
            'definition' => AutomationDefinitionData::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Automations that apply to the ticket: workspace-wide ones and those for its desk.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function for(Builder $query, Ticket $ticket): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('desk_id')->orWhere('desk_id', $ticket->desk_id));
    }

    /**
     * Automations the user may manage: all for admins, else those limited to a desk they lead.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $workspace = Workspace::current();

        if ($workspace !== null && $user->workspaceRole($workspace)?->isAdmin() === true) {
            return;
        }

        $query->whereIn('desk_id', $user->desks()->wherePivot('role', DeskRole::LEAD)->select('desks.id'));
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Desk, $this>
     */
    public function desk(): BelongsTo
    {
        return $this->belongsTo(Desk::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'aut_';
    }
}
