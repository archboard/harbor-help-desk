<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\DeskFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A help desk inside a workspace, e.g. one school in a district.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $archived_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\DeskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk whereWorkspaceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Desk withoutTrashed()
 *
 * @property-read DeskUser|null $pivot
 *
 * @method static Builder<static>|Desk visibleTo(\App\Models\User $user)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Desk extends Model
{
    /** @use HasFactory<DeskFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids, SoftDeletes;

    public const string DELETED_AT = 'archived_at';

    public function idPrefix(): string
    {
        return 'dsk_';
    }

    /**
     * Limit to the desks the user can see in the current workspace: all of them for
     * workspace admins, otherwise only the desks they're on.
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

        $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->getKey()));
    }

    /**
     * The agents on this desk, with their desk role on the pivot.
     *
     * @return BelongsToMany<User, $this, DeskUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'desk_user')
            ->using(DeskUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }
}
