<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\SetAutomationEnabled;
use App\Data\Automations\AutomationData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Http\Request;

class SetAutomationEnabledController extends Controller
{
    public function __invoke(Request $request, Automation $automation, SetAutomationEnabled $setAutomationEnabled): AutomationData
    {
        return AutomationData::from($setAutomationEnabled->handle($request->user(), $automation, $request->all()));
    }
}
