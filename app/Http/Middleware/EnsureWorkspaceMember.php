<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects authenticated users who are not members of the current workspace.
 */
class EnsureWorkspaceMember
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isMember = $request->user()
            ?->workspaces()
            ->whereKey(Workspace::current()?->getKey())
            ->exists();

        abort_unless($isMember, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
