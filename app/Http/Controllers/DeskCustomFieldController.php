<?php

namespace App\Http\Controllers;

use App\Actions\Desks\SyncDeskCustomFields;
use App\Http\Flash;
use App\Models\Desk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeskCustomFieldController extends Controller
{
    /**
     * Replace the desk's custom fields with `field_ids`.
     */
    public function update(Request $request, Desk $desk, SyncDeskCustomFields $syncDeskCustomFields): RedirectResponse
    {
        $syncDeskCustomFields->handle($request->user(), $desk, $request->all());

        Flash::success(__('Custom fields saved.'));

        return back();
    }
}
