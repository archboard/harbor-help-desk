<?php

namespace App\Actions\ApiTokens;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\PersonalAccessTokenResult;

class CreatePersonalAccessToken
{
    /**
     * Issue a personal access token scoped to the given workspaces. Workspaces the user
     * doesn't belong to are dropped by the scope repository. The plain-text token is only
     * available on the result.
     *
     * @param  list<string>  $workspaceIds
     * @return PersonalAccessTokenResult<mixed>
     */
    public function handle(User $user, string $name, array $workspaceIds): PersonalAccessTokenResult
    {
        return $user->createToken(
            $name,
            array_map(fn (string $id): string => Workspace::TOKEN_SCOPE_PREFIX.$id, $workspaceIds),
        );
    }
}
