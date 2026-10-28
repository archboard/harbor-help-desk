<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\ReorderAutomations;
use App\Data\Automations\AutomationData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReorderAutomationsController extends Controller
{
    /**
     * @return Collection<int, AutomationData>
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, ReorderAutomations $reorderAutomations): Collection
    {
        return AutomationData::collect($reorderAutomations->handle($user, $request->all()));
    }
}
