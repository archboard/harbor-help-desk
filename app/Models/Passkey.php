<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Laravel\Passkeys\Passkey as BasePasskey;

/**
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string $credential_id
 * @property array<array-key, mixed> $credential
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string|null $authenticator
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereCredential($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereCredentialId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereLastUsedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Passkey whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Passkey extends BasePasskey
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    use HasScru128Ids;

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'pky_';
    }
}
