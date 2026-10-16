<?php

namespace App\OAuth;

use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Bridge\ScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

/**
 * Accepts `workspace:{id}` scopes alongside Passport's registered scopes, keeping only
 * the workspaces the token's user belongs to.
 */
class WorkspaceScopeRepository extends ScopeRepository
{
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        return $identifier !== '' && $this->isWorkspaceScope($identifier)
            ? new Scope($identifier)
            : parent::getScopeEntityByIdentifier($identifier);
    }

    /**
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null,
    ): array {
        [$workspaceScopes, $otherScopes] = collect($scopes)
            ->partition(fn (ScopeEntityInterface $scope): bool => $this->isWorkspaceScope($scope->getIdentifier()));

        $memberScopes = $userIdentifier === null ? [] : (User::find($userIdentifier)?->workspaces()
            ->get()
            ->map(fn (Workspace $workspace): string => $workspace->tokenScope())
            ->all() ?? []);

        return [
            ...parent::finalizeScopes($otherScopes->values()->all(), $grantType, $clientEntity, $userIdentifier, $authCodeId),
            ...$workspaceScopes
                ->filter(fn (ScopeEntityInterface $scope): bool => in_array($scope->getIdentifier(), $memberScopes, true))
                ->values()
                ->all(),
        ];
    }

    protected function isWorkspaceScope(string $identifier): bool
    {
        return str_starts_with($identifier, Workspace::TOKEN_SCOPE_PREFIX)
            && strlen($identifier) > strlen(Workspace::TOKEN_SCOPE_PREFIX);
    }
}
