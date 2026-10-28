<?php

namespace App\Http\Controllers;

use App\Actions\Automations\DuplicateAutomation;
use App\Http\Flash;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class DuplicateAutomationController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Automation $automation, DuplicateAutomation $duplicateAutomation): RedirectResponse
    {
        $duplicateAutomation->handle($user, $automation);

        Flash::success(__('Automation duplicated. The copy is disabled.'));

        return back();
    }
}
