<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\ImportWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImportWorkspaceController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ImportWorkspace $importWorkspace): RedirectResponse
    {
        $importWorkspace->handle($user, $request->all());

        Flash::success(__('The import is running. You will get an email when it is done.'));

        return back();
    }
}
