<?php

namespace App\Http\Controllers;

use App\Actions\Desks\RemoveDeskMember;
use App\Actions\Desks\SetDeskMember;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeskMemberController extends Controller
{
    /**
     * Add the user to the desk, or change their role.
     */
    public function update(Request $request, Desk $desk, User $user, SetDeskMember $setDeskMember): RedirectResponse
    {
        $setDeskMember->handle($request->user(), $desk, $user, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desk member saved.')]);

        return back();
    }

    public function destroy(Request $request, Desk $desk, User $user, RemoveDeskMember $removeDeskMember): RedirectResponse
    {
        $removeDeskMember->handle($request->user(), $desk, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Desk member removed.')]);

        return back();
    }
}
