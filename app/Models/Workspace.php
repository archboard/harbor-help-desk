<?php

namespace App\Models;

use App\Actions\Mailboxes\CreateHostedMailbox;
use App\Casts\WorkspaceSettingsCast;
use App\Data\Workspaces\WorkspaceSettings;
use Carbon\CarbonImmutable;
use Database\Factories\WorkspaceFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Spatie\Multitenancy\Concerns\UsesMultitenancyConfig;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;

/**
 * ponytail: settings are flat prefixed keys (`mail_…`, `attachments_…`); split into nested Data
 * groups when a group grows past a handful.
 *
 * @property string $id
 * @property string $name
 * @property string $subdomain
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property int $next_ticket_number
 * @property WorkspaceSettings $settings
 * @property-read Collection<int, Desk> $desks
 * @property-read int|null $desks_count
 * @property-read Collection<int, Mailbox> $mailboxes
 * @property-read int|null $mailboxes_count
 * @property-read WorkspaceUser|null $pivot
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereNextTicketNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereSubdomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Workspace whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Workspace extends Model implements IsTenant
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasScru128Ids, ImplementsTenant, UsesMultitenancyConfig;

    /**
     * OAuth scopes of this form grant a token access to one workspace.
     */
    public const string TOKEN_SCOPE_PREFIX = 'workspace:';

    /*
    |--------------------------------------------------------------------------
    | Booting
    |--------------------------------------------------------------------------
    */

    /**
     * Every workspace starts with a default desk, so it always has at least one.
     */
    protected static function booted(): void
    {
        static::created(function (Workspace $workspace): void {
            $desk = $workspace->desks()->create(['name' => $workspace->name, 'slug' => 'default']);

            app(CreateHostedMailbox::class)->handle($workspace, $desk);
        });
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
        return ['settings' => WorkspaceSettingsCast::class];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The agents who belong to this workspace, with their role on the pivot.
     *
     * @return BelongsToMany<User, $this, WorkspaceUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->using(WorkspaceUser::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Desk, $this>
     */
    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    /**
     * @return HasMany<Mailbox, $this>
     */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'wsp_';
    }

    /**
     * The OAuth scope that grants a token access to this workspace.
     */
    public function tokenScope(): string
    {
        return self::TOKEN_SCOPE_PREFIX.$this->getKey();
    }

    /**
     * Whether any agent belongs to the workspace yet. Until then it can still be claimed.
     */
    public function hasMembers(): bool
    {
        return $this->users()->exists();
    }

    /**
     * An absolute URL on the workspace's own host: its subdomain in the cloud, else `APP_URL`.
     */
    public function url(string $path = ''): string
    {
        $root = rtrim((string) config('app.url'), '/');

        if (config('harbor.multitenant')) {
            $port = parse_url($root, PHP_URL_PORT);
            $root = parse_url($root, PHP_URL_SCHEME)."://{$this->subdomain}.".config('harbor.domain').($port ? ":{$port}" : '');
        }

        return $root.'/'.ltrim($path, '/');
    }

    /**
     * A signed link, valid for 14 days, that lets the first person claim the workspace as its
     * owner. Relative, so it works on the workspace's own host.
     */
    public function setupUrl(): string
    {
        return $this->url(URL::temporarySignedRoute('setup.start', now()->addDays(14), ['workspace' => $this->getKey()], absolute: false));
    }

    /**
     * Claim the workspace's next ticket number. The single UPDATE is atomic, so concurrent
     * tickets never share a number.
     */
    public static function allocateTicketNumber(string $workspaceId): int
    {
        return (int) DB::selectOne(
            'UPDATE workspaces SET next_ticket_number = next_ticket_number + 1 WHERE id = ? RETURNING next_ticket_number - 1 AS number',
            [$workspaceId],
        )->number;
    }

    /**
     * The mailbox app email (notifications, sign-in links) is sent from: the one in the settings,
     * else the oldest. A chosen mailbox that no longer exists counts as unset.
     */
    public function notificationMailbox(): ?Mailbox
    {
        return $this->execute(function (): ?Mailbox {
            $id = $this->settings->mailNotificationMailboxId;

            return ($id ? Mailbox::query()->find($id) : null) ?? Mailbox::query()->oldest('id')->first();
        });
    }
}
