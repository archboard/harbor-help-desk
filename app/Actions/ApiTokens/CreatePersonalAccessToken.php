<?php

namespace App\Actions\ApiTokens;

use App\Models\User;
use Laravel\Passport\PersonalAccessTokenResult;

class CreatePersonalAccessToken
{
    /**
     * Issue a personal access token. The plain-text token is only available on the result.
     *
     * @return PersonalAccessTokenResult<mixed>
     */
    public function handle(User $user, string $name): PersonalAccessTokenResult
    {
        return $user->createToken($name);
    }
}
