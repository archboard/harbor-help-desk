<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * A database notification, scoped to the workspace it was sent in. `type` is a `NotificationType` value.
 *
 * @property string $workspace_id
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property string $notifiable_id
 * @property array<array-key, mixed> $data
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Model $notifiable
 * @property-read Workspace $workspace
 *
 * @method static \Illuminate\Notifications\DatabaseNotificationCollection<int, static> all($columns = ['*'])
 * @method static \Illuminate\Notifications\DatabaseNotificationCollection<int, static> get($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification query()
 * @method static Builder<static>|DatabaseNotification read()
 * @method static Builder<static>|DatabaseNotification unread()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereNotifiableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereNotifiableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DatabaseNotification whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    use BelongsToWorkspace;
}
