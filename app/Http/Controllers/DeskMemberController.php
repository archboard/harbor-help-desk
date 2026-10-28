<?php

namespace App\Http\Controllers;

use App\Actions\Desks\RemoveDeskMember;
use App\Actions\Desks\SetDeskMember;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeskMemberController extends Controller
{
    /**
     * Add the user to the desk, or change their role.
     */
    public function update(Request $request, #[CurrentUser] User $actor, Desk $desk, User $user, SetDeskMember $setDeskMember): RedirectResponse
    {
        $setDeskMember->handle($actor, $desk, $user, $request->all());

        Flash::success(__('Desk member saved.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $actor, Desk $desk, User $user, RemoveDeskMember $removeDeskMember): RedirectResponse
    {
        $removeDeskMember->handle($actor, $desk, $user);

        Flash::success(__('Desk member removed.'));

        return back();
    }
}
