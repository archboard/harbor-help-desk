<?php

namespace App\Http\Controllers;

use App\Actions\Automations\SetAutomationEnabled;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetAutomationEnabledController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, Automation $automation, SetAutomationEnabled $setAutomationEnabled): RedirectResponse
    {
        $setAutomationEnabled->handle($user, $automation, $request->all());

        return back();
    }
}
