<?php

namespace App\Http\Controllers\Api\V1;

use App\Automations\Catalog;
use App\Data\Automations\AutomationCatalogData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AutomationCatalogController extends Controller
{
    /**
     * Everything the automation builder offers the current user.
     */
    public function __invoke(Request $request, Catalog $catalog): AutomationCatalogData
    {
        Gate::authorize('viewAny', Automation::class);

        return $catalog->build($request->user());
    }
}
