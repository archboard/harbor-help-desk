<?php

namespace App\Actions\ApiTokens;

use App\Data\ApiTokens\CreatePersonalAccessTokenData;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Validation\Rule;
use Laravel\Passport\PersonalAccessTokenResult;

class CreatePersonalAccessToken
{
    /**
     * Issue a personal access token scoped to `workspaces`, which must be ones the user belongs
     * to. The scope repository drops any they leave later. The plain-text token is only
     * available on the result.
     *
     * @param  array<string, mixed>  $input
     * @return PersonalAccessTokenResult<mixed>
     */
    public function handle(User $user, array $input): PersonalAccessTokenResult
    {
        $data = CreatePersonalAccessTokenData::validateAndCreateWith($input, [
            'workspaces.*' => [Rule::in($user->workspaces()->pluck('workspaces.id'))],
        ]);

        return $user->createToken(
            $data->name,
            array_map(fn (string $id): string => Workspace::TOKEN_SCOPE_PREFIX.$id, $data->workspaces),
        );
    }
}
