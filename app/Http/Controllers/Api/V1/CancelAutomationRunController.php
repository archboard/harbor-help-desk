<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\CancelAutomationRun;
use App\Data\Automations\AutomationRunData;
use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class CancelAutomationRunController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, AutomationRun $run, CancelAutomationRun $cancelAutomationRun): JsonResponse
    {
        return response()->json(AutomationRunData::from($cancelAutomationRun->handle($user, $run)->load(['automation', 'ticket'])));
    }
}
