<?php

namespace App\TenantFinders;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

/**
 * Finds the workspace from the subdomain of `harbor.domain`, e.g. `acme.harbor.help`.
 */
class SubdomainWorkspaceFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Workspace
    {
        $suffix = '.'.config('harbor.domain');
        $host = $request->getHost();

        if (! Str::endsWith($host, $suffix)) {
            return null;
        }

        $subdomain = Str::beforeLast($host, $suffix);

        if (Str::contains($subdomain, '.')) {
            return null;
        }

        return Workspace::query()->where('subdomain', $subdomain)->first();
    }
}
