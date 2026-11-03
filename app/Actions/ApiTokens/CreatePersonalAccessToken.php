<?php

namespace App\Actions\ApiTokens;

use App\Data\ApiTokens\CreatePersonalAccessTokenData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
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

        $result = $user->createToken(
            $data->name,
            array_map(fn (string $id): string => Workspace::TOKEN_SCOPE_PREFIX.$id, $data->workspaces),
        );

        // A token isn't tied to one workspace, so each workspace it reaches gets the event.
        foreach ($data->workspaces as $workspaceId) {
            AuditEvent::record(AuditAction::API_TOKEN_CREATED, $user, properties: ['token_id' => $result->getToken()?->getKey()], workspaceId: $workspaceId);
        }

        return $result;
    }
}
