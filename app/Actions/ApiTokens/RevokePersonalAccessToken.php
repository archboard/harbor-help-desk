<?php

namespace App\Actions\ApiTokens;

use App\Models\User;

class RevokePersonalAccessToken
{
    /**
     * Revoke one of the user's tokens. Tokens owned by other users are not found.
     */
    public function handle(User $user, string $tokenId): void
    {
        $user->tokens()->whereKey($tokenId)->firstOrFail()->revoke();
    }
}
