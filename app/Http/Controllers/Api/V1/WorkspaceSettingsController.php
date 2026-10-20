<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\UpdateWorkspaceSettings;
use App\Data\Workspaces\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceSettingsController extends Controller
{
    public function update(Request $request, UpdateWorkspaceSettings $updateWorkspaceSettings): WorkspaceData
    {
        return WorkspaceData::from($updateWorkspaceSettings->handle($request->user(), Workspace::current(), $request->all()));
    }
}
