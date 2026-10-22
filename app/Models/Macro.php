<?php

namespace App\Models;

use App\Enums\MacroScope;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\MacroFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A canned response that can also change a ticket. Personal when `user_id` is set, otherwise
 * shared with the workspace, or with one desk when `desk_id` is set.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $desk_id
 * @property string|null $user_id
 * @property string $name
 * @property string|null $body
 * @property bool $body_is_internal
 * @property array<string, mixed> $actions
 * @property string|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Desk|null $desk
 *
 * @method static \Database\Factories\MacroFactory factory($count = null, $state = [])
 * @method static Builder<static>|Macro newModelQuery()
 * @method static Builder<static>|Macro newQuery()
 * @method static Builder<static>|Macro query()
 * @method static Builder<static>|Macro usableOn(\App\Models\Ticket $ticket)
 * @method static Builder<static>|Macro visibleTo(\App\Models\User $user)
 * @method static Builder<static>|Macro whereActions($value)
 * @method static Builder<static>|Macro whereBody($value)
 * @method static Builder<static>|Macro whereBodyIsInternal($value)
 * @method static Builder<static>|Macro whereCreatedAt($value)
 * @method static Builder<static>|Macro whereCreatedBy($value)
 * @method static Builder<static>|Macro whereDeskId($value)
 * @method static Builder<static>|Macro whereId($value)
 * @method static Builder<static>|Macro whereName($value)
 * @method static Builder<static>|Macro whereUpdatedAt($value)
 * @method static Builder<static>|Macro whereUserId($value)
 * @method static Builder<static>|Macro whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Macro extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<MacroFactory> */
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
            'body_is_internal' => 'boolean',
            'actions' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * The user's personal macros, and the shared ones for the workspace or a desk they can see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->getKey())
            ->orWhere(fn (Builder $query) => $query
                ->whereNull('user_id')
                ->where(fn (Builder $query) => $query
                    ->whereNull('desk_id')
                    ->orWhereIn('desk_id', Desk::visibleTo($user)->select('desks.id')))));
    }

    /**
     * Macros that aren't tied to another desk than the ticket's.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function usableOn(Builder $query, Ticket $ticket): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('desk_id')->orWhere('desk_id', $ticket->desk_id));
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

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'mac_';
    }

    public function kind(): MacroScope
    {
        return match (true) {
            $this->user_id !== null => MacroScope::PERSONAL,
            $this->desk_id !== null => MacroScope::DESK,
            default => MacroScope::WORKSPACE,
        };
    }
}
