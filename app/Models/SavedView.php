<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\SavedViewFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named set of ticket list filters. Private to its owner unless shared with the workspace.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $user_id
 * @property string $name
 * @property array<array-key, mixed> $filters
 * @property bool $is_shared
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\SavedViewFactory factory($count = null, $state = [])
 * @method static Builder<static>|SavedView newModelQuery()
 * @method static Builder<static>|SavedView newQuery()
 * @method static Builder<static>|SavedView query()
 * @method static Builder<static>|SavedView visibleTo(\App\Models\User $user)
 * @method static Builder<static>|SavedView whereCreatedAt($value)
 * @method static Builder<static>|SavedView whereFilters($value)
 * @method static Builder<static>|SavedView whereId($value)
 * @method static Builder<static>|SavedView whereIsShared($value)
 * @method static Builder<static>|SavedView whereName($value)
 * @method static Builder<static>|SavedView whereUpdatedAt($value)
 * @method static Builder<static>|SavedView whereUserId($value)
 * @method static Builder<static>|SavedView whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class SavedView extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<SavedViewFactory> */
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
            'filters' => 'array',
            'is_shared' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * The user's own views and every shared one.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $query) => $query->where('user_id', $user->getKey())->orWhere('is_shared', true));
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
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
        return 'svw_';
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->getKey();
    }
}
