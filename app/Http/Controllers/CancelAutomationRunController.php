<?php

namespace App\Http\Controllers;

use App\Actions\Automations\CancelAutomationRun;
use App\Http\Flash;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class CancelAutomationRunController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, AutomationRun $run, CancelAutomationRun $cancelAutomationRun): RedirectResponse
    {
        $cancelAutomationRun->handle($user, $run);

        Flash::success(__('Run cancelled.'));

        return back();
    }
}
