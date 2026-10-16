<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects API requests unless the token was granted the current workspace and its user is
 * still a member, so removing someone from a workspace also cuts off their existing tokens.
 */
class EnsureTokenCanAccessWorkspace
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = Workspace::current();

        // ponytail: tokenCan() also passes `*`, which we never issue (Passport strips it from auth code tokens).
        $canAccess = $user !== null
            && $workspace !== null
            && $user->tokenCan($workspace->tokenScope())
            && $user->workspaces()->whereKey($workspace->getKey())->exists();

        abort_unless($canAccess, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
