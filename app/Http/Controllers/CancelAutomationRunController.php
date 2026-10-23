<?php

namespace App\Http\Controllers;

use App\Actions\Automations\CancelAutomationRun;
use App\Http\Flash;
use App\Models\AutomationRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CancelAutomationRunController extends Controller
{
    public function __invoke(Request $request, AutomationRun $run, CancelAutomationRun $cancelAutomationRun): RedirectResponse
    {
        $cancelAutomationRun->handle($request->user(), $run);

        Flash::success(__('Run cancelled.'));

        return back();
    }
}
