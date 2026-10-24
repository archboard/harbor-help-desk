<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalEnabled
{
    /**
     * 404 unless the current workspace has its customer portal switched on.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Workspace::current()?->settings->portalEnabled, Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
