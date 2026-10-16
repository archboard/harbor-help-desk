<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\TenantFinders\SingleWorkspaceFinder;
use App\TenantFinders\SubdomainWorkspaceFinder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveWorkspace
{
    /**
     * Make the request's workspace current, or 404 when there is none.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $finder = config('harbor.multitenant')
            ? app(SubdomainWorkspaceFinder::class)
            : app(SingleWorkspaceFinder::class);

        $workspace = Workspace::current() ?? $finder->findForRequest($request);

        abort_if($workspace === null, Response::HTTP_NOT_FOUND);

        $workspace->makeCurrent();

        return $next($request);
    }
}
