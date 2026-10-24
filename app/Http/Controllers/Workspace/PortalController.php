<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\PortalAccess;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Show the customer portal settings. The current values come from the shared `workspace.settings` prop.
     */
    public function edit(): Response
    {
        Gate::authorize('update', Workspace::current());

        return Inertia::render('workspace/Portal', [
            'accessOptions' => PortalAccess::options(),
        ]);
    }
}
