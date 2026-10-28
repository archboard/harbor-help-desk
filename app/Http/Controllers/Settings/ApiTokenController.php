<?php

namespace App\Http\Controllers\Settings;

use App\Actions\ApiTokens\CreatePersonalAccessToken;
use App\Actions\ApiTokens\RevokePersonalAccessToken;
use App\Data\ApiTokens\ApiTokenData;
use App\Data\Workspaces\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('settings/ApiTokens', [
            'workspaces' => WorkspaceData::collect($user->workspaces),
            'tokens' => ApiTokenData::collect($user
                ->tokens()
                ->with('client')
                ->where('revoked', false)
                ->latest()
                ->get()
                ->filter(fn (Token $token): bool => $token->client->hasGrantType('personal_access'))
                ->values()),
        ]);
    }

    /**
     * Create a personal access token and flash its plain-text value once.
     */
    public function store(Request $request, #[CurrentUser] User $user, CreatePersonalAccessToken $createToken): RedirectResponse
    {
        $result = $createToken->handle($user, $request->all());

        Inertia::flash('apiToken', $result->accessToken);

        return back();
    }

    /**
     * Revoke one of the user's personal access tokens.
     */
    public function destroy(#[CurrentUser] User $user, string $tokenId, RevokePersonalAccessToken $revokeToken): RedirectResponse
    {
        $revokeToken->handle($user, $tokenId);

        Flash::success(__('Token revoked.'));

        return back();
    }
}
