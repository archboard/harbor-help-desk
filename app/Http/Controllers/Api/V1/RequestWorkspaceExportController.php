<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exports\RequestWorkspaceExport;
use App\Data\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class RequestWorkspaceExportController extends Controller
{
    /**
     * Queue an export of the whole workspace. Admins only.
     */
    public function __invoke(#[CurrentUser] User $user, RequestWorkspaceExport $requestWorkspaceExport): ExportData
    {
        return ExportData::from($requestWorkspaceExport->handle($user));
    }
}
