<?php

namespace App\Data\ApiTokens;

use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Laravel\Passport\Token;
use Spatie\LaravelData\Data;

/**
 * One of the requesting user's personal access tokens. Its workspaces are named from the
 * user's memberships, so scopes for workspaces they've left are dropped.
 */
class ApiTokenData extends Data
{
    /**
     * @param  array<int, string>  $workspaces
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public array $workspaces,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $expiresAt,
    ) {}

    public static function fromModel(Token $token): self
    {
        return new self(
            id: $token->id,
            name: $token->name,
            workspaces: request()->user()->workspaces
                ->filter(fn (Workspace $workspace): bool => in_array($workspace->tokenScope(), $token->scopes, true))
                ->map(fn (Workspace $workspace): string => $workspace->name)
                ->values()
                ->all(),
            createdAt: $token->created_at,
            expiresAt: $token->expires_at,
        );
    }
}
