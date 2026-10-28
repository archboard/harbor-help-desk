<?php

namespace App\Http\Controllers\Api\Platform;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateProvisionedWorkspace;
use App\Data\Workspaces\ProvisionedWorkspaceData;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkspaceController extends Controller
{
    public function store(Request $request, CreateWorkspace $createWorkspace): ProvisionedWorkspaceData
    {
        return ProvisionedWorkspaceData::from($createWorkspace->handle($request->all()));
    }

    /**
     * Send `{}` to reissue the setup link of a workspace that has no owner yet.
     */
    public function update(Request $request, Workspace $workspace, UpdateProvisionedWorkspace $updateWorkspace): ProvisionedWorkspaceData
    {
        return ProvisionedWorkspaceData::from($updateWorkspace->handle($workspace, $request->all()));
    }

    public function destroy(Workspace $workspace, DeleteWorkspace $deleteWorkspace): Response
    {
        $deleteWorkspace->handle($workspace);

        return response()->noContent();
    }
}
