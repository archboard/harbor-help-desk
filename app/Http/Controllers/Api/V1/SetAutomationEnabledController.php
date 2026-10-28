<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\SetAutomationEnabled;
use App\Data\Automations\AutomationData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class SetAutomationEnabledController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, Automation $automation, SetAutomationEnabled $setAutomationEnabled): AutomationData
    {
        return AutomationData::from($setAutomationEnabled->handle($user, $automation, $request->all()));
    }
}
