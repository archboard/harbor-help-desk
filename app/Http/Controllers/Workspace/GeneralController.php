<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
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

    public function update(Request $request, #[CurrentUser] User $user, UpdateWorkspace $updateWorkspace): RedirectResponse
    {
        $updateWorkspace->handle($user, Workspace::current(), $request->all());

        Flash::success(__('Workspace updated.'));

        return back();
    }
}
