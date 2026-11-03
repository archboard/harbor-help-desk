<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Exports\ListExports;
use App\Data\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class DataPrivacyController extends Controller
{
    /**
     * The workspace's data exports. Admins only.
     */
    public function index(#[CurrentUser] User $user, ListExports $listExports): Response
    {
        return Inertia::render('workspace/DataPrivacy', [
            'exports' => Inertia::scroll(ExportData::collect($listExports->handle($user))),
        ]);
    }
}
