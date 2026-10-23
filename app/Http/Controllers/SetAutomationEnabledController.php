<?php

namespace App\Http\Controllers;

use App\Actions\Automations\SetAutomationEnabled;
use App\Models\Automation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetAutomationEnabledController extends Controller
{
    public function __invoke(Request $request, Automation $automation, SetAutomationEnabled $setAutomationEnabled): RedirectResponse
    {
        $setAutomationEnabled->handle($request->user(), $automation, $request->all());

        return back();
    }
}
