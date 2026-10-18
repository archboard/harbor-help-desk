<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    /**
     * The workspace the token is being used on.
     */
    public function show(): WorkspaceResource
    {
        return new WorkspaceResource(Workspace::current());
    }

    public function update(Request $request, UpdateWorkspace $updateWorkspace): WorkspaceResource
    {
        return new WorkspaceResource($updateWorkspace->handle($request->user(), Workspace::current(), $request->all()));
    }
}
