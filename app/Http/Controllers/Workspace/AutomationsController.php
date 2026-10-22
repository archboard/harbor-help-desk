<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AutomationsController extends Controller
{
    /**
     * Show the automation settings, which only exist self-hosted. The current values come from the shared `workspace.settings` prop.
     */
    public function edit(): Response
    {
        abort_if(config('harbor.multitenant'), 404);

        Gate::authorize('update', Workspace::current());

        return Inertia::render('workspace/Automations');
    }
}
