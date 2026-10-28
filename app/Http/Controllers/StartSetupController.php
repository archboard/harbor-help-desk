<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartSetupController extends Controller
{
    /**
     * Open the install page for the workspace from a signed setup link. The link names its
     * workspace, since a relative signature alone would also pass on another workspace's host.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $workspace = Workspace::current();

        abort_if($request->query('workspace') !== $workspace->getKey() || $workspace->hasMembers(), Response::HTTP_NOT_FOUND);

        $request->session()->put('setup_workspace_id', $workspace->getKey());

        return to_route('install');
    }
}
