<?php

namespace App\Http\Middleware;

use App\TenantFinders\SingleWorkspaceFinder;
use App\TenantFinders\SubdomainWorkspaceFinder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    /**
     * Make the workspace found from the request current, or 404 when there is none. A workspace
     * left current by an earlier request in the same process is never reused.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $finder = config('harbor.multitenant')
            ? app(SubdomainWorkspaceFinder::class)
            : app(SingleWorkspaceFinder::class);

        $workspace = $finder->findForRequest($request);

        abort_if($workspace === null, Response::HTTP_NOT_FOUND);

        $workspace->makeCurrent();

        return $next($request);
    }
}
