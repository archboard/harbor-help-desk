<?php

namespace App\Http\Controllers;

use App\Actions\Desks\SyncDeskCustomFields;
use App\Data\CustomFields\CustomFieldData;
use App\Data\Desks\DeskData;
use App\Http\Flash;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DeskCustomFieldController extends Controller
{
    public function edit(#[CurrentUser] User $user, Desk $desk): Response
    {
        Gate::authorize('view', $desk);

        return Inertia::render('desks/CustomFields', [
            'desk' => DeskData::from($desk->load('customFields')),
            'customFields' => CustomFieldData::collect(CustomField::query()->orderBy('position')->orderBy('id')->get()),
            'can' => ['update' => $user->can('update', $desk)],
        ]);
    }

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
