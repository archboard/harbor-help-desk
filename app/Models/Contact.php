<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\ContactFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;

/**
 * An end customer of a workspace who requests help. Contacts are not users: they sign in to the customer portal by emailed link, on the `contact` guard, and have no password.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\ContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereWorkspaceId($value)
 *
 * @property-read Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Contact extends Model implements Authenticatable
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<ContactFactory> */
    use AuthenticatableTrait, BelongsToWorkspace, HasFactory, HasScru128Ids, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The tickets this contact requested.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Contacts have no remember-me token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function idPrefix(): string
    {
        return 'ctc_';
    }

    /**
     * The workspace's contact with this email, matched case-insensitively, or a new one.
     *
     * ponytail: two concurrent tickets from a brand-new email can race; the unique index
     * rejects the second. Retry on UniqueConstraintViolationException if that shows up.
     */
    public static function findOrCreateByEmail(string $email, string $name): self
    {
        return self::query()->whereRaw('lower(email) = lower(?)', [$email])->first()
            ?? self::create(['name' => $name, 'email' => $email]);
    }
}
