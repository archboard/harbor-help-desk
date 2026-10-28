<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\UpdateWorkspace;
use App\Data\Workspaces\WorkspaceData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    /**
     * The workspace the token is being used on.
     */
    public function show(): WorkspaceData
    {
        return WorkspaceData::from(Workspace::current());
    }

    public function update(Request $request, #[CurrentUser] User $user, UpdateWorkspace $updateWorkspace): WorkspaceData
    {
        return WorkspaceData::from($updateWorkspace->handle($user, Workspace::current(), $request->all()));
    }
}
