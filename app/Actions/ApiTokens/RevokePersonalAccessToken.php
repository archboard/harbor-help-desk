<?php

namespace App\Actions\ApiTokens;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;

class RevokePersonalAccessToken
{
    /**
     * Revoke one of the user's tokens. Tokens owned by other users are not found.
     */
    public function handle(User $user, string $tokenId): void
    {
        $token = $user->tokens()->whereKey($tokenId)->firstOrFail();
        $token->revoke();

        $workspaceIds = collect($token->scopes)
            ->filter(fn (string $scope): bool => str_starts_with($scope, Workspace::TOKEN_SCOPE_PREFIX))
            ->map(fn (string $scope): string => substr($scope, strlen(Workspace::TOKEN_SCOPE_PREFIX)));

        foreach (Workspace::query()->whereKey($workspaceIds->all())->pluck('id') as $workspaceId) {
            AuditEvent::record(AuditAction::API_TOKEN_REVOKED, $user, properties: ['token_id' => $tokenId], workspaceId: $workspaceId);
        }
    }
}
