<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Exports\RequestWorkspaceExport;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class RequestWorkspaceExportController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, RequestWorkspaceExport $requestWorkspaceExport): RedirectResponse
    {
        $requestWorkspaceExport->handle($user);

        Flash::success(__('The export is being prepared. You will get an email when it is ready.'));

        return back();
    }
}
