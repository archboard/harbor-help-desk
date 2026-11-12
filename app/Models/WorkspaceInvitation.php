<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceInvitationFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * An offer to join the workspace as `role`, and optionally a desk, sent to an email address.
 *
 * Accepting it deletes it. There is one per address in a workspace.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $email
 * @property WorkspaceRole $role
 * @property string|null $desk_id
 * @property string|null $invited_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Desk|null $desk
 * @property-read User|null $invitedBy
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\WorkspaceInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereDeskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereInvitedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkspaceInvitation whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class WorkspaceInvitation extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WorkspaceInvitationFactory> */
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
            'role' => WorkspaceRole::class,
        ];
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
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'inv_';
    }

    /**
     * When the emailed link stops working: 7 days after the invitation was created or last resent.
     */
    public function acceptExpiresAt(): CarbonImmutable
    {
        return $this->created_at->addDays(7);
    }

    /**
     * A signed link to accept the invitation. Relative, so it works on the workspace's own host.
     */
    public function acceptUrl(): string
    {
        return $this->workspace->url(URL::temporarySignedRoute('invitations.show', $this->acceptExpiresAt(), ['invitation' => $this->getKey()], absolute: false));
    }
}
