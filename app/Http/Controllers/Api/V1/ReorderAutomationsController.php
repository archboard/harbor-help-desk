<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\ReorderAutomations;
use App\Data\Automations\AutomationData;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReorderAutomationsController extends Controller
{
    /**
     * @return Collection<int, AutomationData>
     */
    public function __invoke(Request $request, ReorderAutomations $reorderAutomations): Collection
    {
        return AutomationData::collect($reorderAutomations->handle($request->user(), $request->all()));
    }
}
