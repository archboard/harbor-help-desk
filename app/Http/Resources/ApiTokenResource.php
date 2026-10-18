<?php

namespace App\Http\Resources;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Passport\Token;

/**
 * One of the requesting user's personal access tokens. Its workspaces are named from the
 * user's memberships, so scopes for workspaces they've left are dropped.
 *
 * @mixin Token
 */
class ApiTokenResource extends JsonResource
{
    /**
     * @return array{id: string, name: string|null, workspaces: array<int, string>, created_at: string|null, expires_at: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'workspaces' => $request->user()->workspaces
                ->filter(fn (Workspace $workspace): bool => in_array($workspace->tokenScope(), $this->scopes, true))
                ->map(fn (Workspace $workspace): string => $workspace->name)
                ->values()
                ->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
