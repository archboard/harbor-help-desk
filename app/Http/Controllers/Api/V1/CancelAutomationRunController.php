<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\CancelAutomationRun;
use App\Data\Automations\AutomationRunData;
use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelAutomationRunController extends Controller
{
    public function __invoke(Request $request, AutomationRun $run, CancelAutomationRun $cancelAutomationRun): JsonResponse
    {
        return response()->json(AutomationRunData::from($cancelAutomationRun->handle($request->user(), $run)->load(['automation', 'ticket'])));
    }
}
