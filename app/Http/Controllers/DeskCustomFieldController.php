<?php

namespace App\Http\Controllers;

use App\Actions\Desks\SyncDeskCustomFields;
use App\Models\Desk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeskCustomFieldController extends Controller
{
    /**
     * Replace the desk's custom fields with `field_ids`.
     */
    public function update(Request $request, Desk $desk, SyncDeskCustomFields $syncDeskCustomFields): RedirectResponse
    {
        $syncDeskCustomFields->handle($request->user(), $desk, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom fields saved.')]);

        return back();
    }
}
