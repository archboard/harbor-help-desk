<?php

namespace App\Models;

use App\Enums\DeskRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership pivot that casts the role to {@see DeskRole}.
 *
 * @property DeskRole $role
 * @property string $workspace_id
 * @property string $desk_id
 * @property string $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereDeskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereWorkspaceId($value)
 *
 * @property bool $in_rotation
 * @property CarbonImmutable|null $last_assigned_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereInRotation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DeskUser whereLastAssignedAt($value)
 *
 * @mixin \Eloquent
 */
class DeskUser extends Pivot
{
    protected $table = 'desk_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => DeskRole::class,
            'in_rotation' => 'boolean',
            'last_assigned_at' => 'datetime',
        ];
    }
}
