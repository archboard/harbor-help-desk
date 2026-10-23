<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\DuplicateAutomation;
use App\Data\Automations\AutomationData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Http\Request;

class DuplicateAutomationController extends Controller
{
    public function __invoke(Request $request, Automation $automation, DuplicateAutomation $duplicateAutomation): AutomationData
    {
        return AutomationData::from($duplicateAutomation->handle($request->user(), $automation));
    }
}
