<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One admin or configuration change, append-only. Properties hold ids and changed setting keys,
 * never names, emails, bodies or secrets.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property AuditAction $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<array-key, mixed> $properties
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 * @property-read Model|null $actor
 * @property-read Workspace $workspace
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereActorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereSubjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditEvent whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class AuditEvent extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    use BelongsToWorkspace, HasScru128Ids;

    public const null UPDATED_AT = null;

    /*
    |--------------------------------------------------------------------------
    | Booting
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit events cannot be deleted.'));
    }

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
            'action' => AuditAction::class,
            'properties' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The user who made the change. Null when Harbor itself did.
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'aud_';
    }

    /**
     * Record a change in the current workspace, or in `$workspaceId` for changes that aren't tied
     * to one, such as API tokens. Returns null when there is no workspace to record it in. The IP
     * is the request's, so it is null for jobs and commands.
     *
     * @param  array<string, mixed>  $properties  Ids and changed setting keys only, never PII.
     */
    public static function record(AuditAction $action, ?User $actor, ?Model $subject = null, array $properties = [], ?string $workspaceId = null): ?self
    {
        $workspaceId ??= Workspace::current()?->getKey();

        if ($workspaceId === null) {
            return null;
        }

        return self::create([
            'workspace_id' => $workspaceId,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip' => request()->ip(),
        ]);
    }

    public function actorName(): string
    {
        return $this->actor?->getAttribute('name') ?? ($this->actor_type === null ? __('Harbor') : __('A deleted user'));
    }
}
