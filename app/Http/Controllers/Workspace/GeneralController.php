<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Install\CheckServices;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Data\Install\SystemStatusData;
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
        // Infrastructure is the operator's job in the cloud, so only self-hosted admins see the status.
        return Inertia::render('workspace/General', config('harbor.multitenant') ? [] : [
            'status' => Inertia::defer(fn (): SystemStatusData => new SystemStatusData(config('harbor.version'), app(CheckServices::class)->handle())),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateWorkspace $updateWorkspace): RedirectResponse
    {
        $updateWorkspace->handle($user, Workspace::current(), $request->all());

        Flash::success(__('Workspace updated.'));

        return back();
    }
}
