<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

/**
 * @property string $id
 * @property string $name
 * @property string $subdomain
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Spatie\Multitenancy\TenantCollection<int, static> all($columns = ['*'])
 * @method static \Database\Factories\WorkspaceFactory factory($count = null, $state = [])
 * @method static \Spatie\Multitenancy\TenantCollection<int, static> get($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereSubdomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'subdomain'])]
class Workspace extends Model implements IsTenant
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasScru128Ids, ImplementsTenant, UsesMultitenancyConfig;

    public function idPrefix(): string
    {
        return 'wsp_';
    }

    /**
     * The agents who belong to this workspace, with their role on the pivot.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }
}
