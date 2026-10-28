<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\TenantFinders\SubdomainWorkspaceFinder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstallOpen
{
    /**
     * 404 unless the install page is open: self-hosted before any workspace exists, or in the
     * cloud for the unclaimed workspace on this host whose setup link this session opened.
     * In the cloud it makes that workspace current.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('harbor.multitenant')) {
            abort_if(Workspace::query()->exists(), Response::HTTP_NOT_FOUND);

            return $next($request);
        }

        $workspace = app(SubdomainWorkspaceFinder::class)->findForRequest($request);

        abort_unless(
            $workspace !== null
                && $request->session()->get('setup_workspace_id') === $workspace->getKey()
                && ! $workspace->hasMembers(),
            Response::HTTP_NOT_FOUND,
        );

        $workspace->makeCurrent();

        return $next($request);
    }
}
