<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\SetAway;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetAwayController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, SetAway $setAway): RedirectResponse
    {
        $setAway->handle($user, $request->all());

        return back();
    }
}
