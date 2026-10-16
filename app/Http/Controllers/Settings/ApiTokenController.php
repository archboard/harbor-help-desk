<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApiTokens\CreatePersonalAccessToken;
use App\Actions\ApiTokens\RevokePersonalAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ApiTokenStoreRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class ApiTokenController extends Controller
{
    /**
     * Show the user's personal access tokens.
     */
    public function edit(Request $request): Response
    {
        $workspaces = $request->user()->workspaces()->get(['workspaces.id', 'workspaces.name']);
        $workspaceNames = $workspaces->mapWithKeys(fn (Workspace $workspace): array => [$workspace->tokenScope() => $workspace->name]);

        return Inertia::render('settings/ApiTokens', [
            'workspaces' => $workspaces->map(fn (Workspace $workspace): array => ['id' => $workspace->id, 'name' => $workspace->name]),
            'tokens' => $request->user()
                ->tokens()
                ->with('client')
                ->where('revoked', false)
                ->latest()
                ->get()
                ->filter(fn (Token $token): bool => $token->client->hasGrantType('personal_access'))
                ->map(fn (Token $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'workspaces' => $workspaceNames->only($token->scopes)->values()->all(),
                    'created_at' => $token->created_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Create a personal access token and flash its plain-text value once.
     */
    public function store(ApiTokenStoreRequest $request, CreatePersonalAccessToken $createToken): RedirectResponse
    {
        $result = $createToken->handle(
            $request->user(),
            $request->validated('name'),
            $request->validated('workspaces'),
        );

        Inertia::flash('apiToken', $result->accessToken);

        return back();
    }

    /**
     * Revoke one of the user's personal access tokens.
     */
    public function destroy(Request $request, string $tokenId, RevokePersonalAccessToken $revokeToken): RedirectResponse
    {
        $revokeToken->handle($request->user(), $tokenId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return back();
    }
}
