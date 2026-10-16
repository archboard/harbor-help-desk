<?php

namespace App\TenantFinders;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

/**
 * Self-hosted installs have one workspace, which is always the current one.
 */
class SingleWorkspaceFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Workspace
    {
        return Workspace::query()->oldest('id')->first();
    }
}
