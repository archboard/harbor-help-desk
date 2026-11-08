<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\UpdateWorkspaceSettings;
use App\Data\Workspaces\UpdateWorkspaceSettingsData;
use App\Data\Workspaces\WorkspaceData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class WorkspaceSettingsController extends Controller
{
    #[ApiBody(UpdateWorkspaceSettingsData::class)]
    public function update(Request $request, #[CurrentUser] User $user, UpdateWorkspaceSettings $updateWorkspaceSettings): WorkspaceData
    {
        return WorkspaceData::from($updateWorkspaceSettings->handle($user, Workspace::current(), $request->all()));
    }
}
