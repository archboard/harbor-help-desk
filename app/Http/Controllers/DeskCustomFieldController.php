<?php

namespace App\Http\Controllers;

use App\Actions\Desks\SyncDeskCustomFields;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeskCustomFieldController extends Controller
{
    /**
     * Replace the desk's custom fields with `field_ids`.
     */
    public function update(Request $request, #[CurrentUser] User $user, Desk $desk, SyncDeskCustomFields $syncDeskCustomFields): RedirectResponse
    {
        $syncDeskCustomFields->handle($user, $desk, $request->all());

        Flash::success(__('Custom fields saved.'));

        return back();
    }
}
