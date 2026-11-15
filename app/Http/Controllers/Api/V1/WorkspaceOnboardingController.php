<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Workspaces\OnboardingData;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;

class WorkspaceOnboardingController extends Controller
{
    /**
     * The "Get set up" checklist and whether each step is done. Owners and admins only.
     */
    public function show(): OnboardingData
    {
        $workspace = Workspace::current();

        Gate::authorize('update', $workspace);

        return OnboardingData::forWorkspace($workspace);
    }
}
