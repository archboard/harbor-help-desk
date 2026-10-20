<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\UpdateWorkspaceSettings;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Saves workspace settings for every section page, each submitting only its own keys.
 */
class SettingsController extends Controller
{
    public function update(Request $request, UpdateWorkspaceSettings $updateWorkspaceSettings): RedirectResponse
    {
        $updateWorkspaceSettings->handle($request->user(), Workspace::current(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }
}
