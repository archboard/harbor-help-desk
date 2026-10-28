<?php

namespace App\Http\Controllers\Api\V1;

use App\Automations\Catalog;
use App\Data\Automations\AutomationCatalogData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Facades\Gate;

class AutomationCatalogController extends Controller
{
    /**
     * Everything the automation builder offers the current user.
     */
    public function __invoke(#[CurrentUser] User $user, Catalog $catalog): AutomationCatalogData
    {
        Gate::authorize('viewAny', Automation::class);

        return $catalog->build($user);
    }
}
