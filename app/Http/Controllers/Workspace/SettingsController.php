<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\UpdateWorkspaceSettings;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Saves workspace settings for every section page, each submitting only its own keys.
 */
class SettingsController extends Controller
{
    public function update(Request $request, #[CurrentUser] User $user, UpdateWorkspaceSettings $updateWorkspaceSettings): RedirectResponse
    {
        $updateWorkspaceSettings->handle($user, Workspace::current(), $request->all());

        Flash::success(__('Settings saved.'));

        return back();
    }
}
