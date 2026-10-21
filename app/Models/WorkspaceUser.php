<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\WorkspaceRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Membership pivot that casts the role to {@see WorkspaceRole}.
 *
 * @property WorkspaceRole $role
 * @property string $workspace_id
 * @property string $user_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereWorkspaceId($value)
 *
 * @property array<array-key, mixed> $notification_preferences
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereNotificationPreferences($value)
 *
 * @property CarbonImmutable|null $away_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceUser whereAwayAt($value)
 *
 * @mixin \Eloquent
 */
class WorkspaceUser extends Pivot
{
    protected $table = 'workspace_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'notification_preferences' => 'array',
            'away_at' => 'datetime',
        ];
    }

    /**
     * Whether the member wants the notification on the channel; a choice they haven't made falls back to the type's default.
     */
    public function wantsNotification(NotificationType $type, NotificationChannel $channel): bool
    {
        return $this->notification_preferences[$type->value][$channel->value]
            ?? in_array($channel, $type->defaultChannels(), true);
    }
}
