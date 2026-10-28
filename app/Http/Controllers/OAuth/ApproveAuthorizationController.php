<?php

namespace App\Http\Controllers\OAuth;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Passport\Bridge\Scope;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveAuthorizationController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class ApproveAuthorizationController extends PassportApproveAuthorizationController
{
    /**
     * Approve the authorization request, granting the token the workspaces the user picked.
     */
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $validated = $request->validate([
            'workspaces' => ['required', 'array', 'min:1'],
            'workspaces.*' => ['string', Rule::in($request->user('web')->workspaces()->pluck('workspaces.id'))],
        ]);

        $authRequest = $this->getAuthRequestFromSession($request);

        $authRequest->setScopes([
            ...$authRequest->getScopes(),
            ...array_map(fn (string $id): Scope => new Scope(Workspace::TOKEN_SCOPE_PREFIX.$id), $validated['workspaces']),
        ]);
        $authRequest->setAuthorizationApproved(true);

        return $this->withErrorHandling(fn () => $this->convertResponse(
            $this->server->completeAuthorizationRequest($authRequest, $psrResponse)
        ));
    }
}
