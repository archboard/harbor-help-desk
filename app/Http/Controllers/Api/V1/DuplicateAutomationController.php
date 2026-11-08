<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\DuplicateAutomation;
use App\Data\Automations\AutomationData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class DuplicateAutomationController extends Controller
{
    #[ApiBody]
    public function __invoke(#[CurrentUser] User $user, Automation $automation, DuplicateAutomation $duplicateAutomation): AutomationData
    {
        return AutomationData::from($duplicateAutomation->handle($user, $automation));
    }
}
