<?php

namespace App\Http\Controllers;

use App\Actions\Automations\DuplicateAutomation;
use App\Http\Flash;
use App\Models\Automation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DuplicateAutomationController extends Controller
{
    public function __invoke(Request $request, Automation $automation, DuplicateAutomation $duplicateAutomation): RedirectResponse
    {
        $duplicateAutomation->handle($request->user(), $automation);

        Flash::success(__('Automation duplicated. The copy is disabled.'));

        return back();
    }
}
