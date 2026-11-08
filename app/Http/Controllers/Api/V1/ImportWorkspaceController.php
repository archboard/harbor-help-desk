<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\ImportWorkspace;
use App\Data\Workspaces\ImportWorkspaceData;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportWorkspaceController extends Controller
{
    /**
     * Queue the import of the multipart `file`, a workspace export zip, into the workspace. It must
     * have no tickets or contacts. Admins only.
     */
    #[ApiBody(ImportWorkspaceData::class)]
    #[ApiResponse(status: 202)]
    public function __invoke(Request $request, #[CurrentUser] User $user, ImportWorkspace $importWorkspace): JsonResponse
    {
        $importWorkspace->handle($user, $request->all());

        return response()->json(null, 202);
    }
}
