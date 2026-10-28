<?php

namespace App\Http\Controllers;

use App\Actions\Automations\ReorderAutomations;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReorderAutomationsController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, ReorderAutomations $reorderAutomations): RedirectResponse
    {
        $reorderAutomations->handle($user, $request->all());

        return back();
    }
}
