<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    /**
     * Show the current workspace's settings page.
     */
    public function edit(): Response
    {
        Gate::authorize('update', Workspace::current());

        // The page reads the workspace from the shared `workspace` prop.
        return Inertia::render('workspace/General');
    }

    public function update(Request $request, UpdateWorkspace $updateWorkspace): RedirectResponse
    {
        $updateWorkspace->handle($request->user(), Workspace::current(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Workspace updated.')]);

        return back();
    }
}
