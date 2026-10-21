<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Macros\CreateMacro;
use App\Actions\Macros\DeleteMacro;
use App\Actions\Macros\UpdateMacro;
use App\Data\Desks\DeskData;
use App\Data\Macros\MacroData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\Macro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MacroController extends Controller
{
    /**
     * The user's personal macros and the shared ones they can see, with what they may manage.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Macro::class);

        $user = $request->user();
        $macros = Macro::visibleTo($user)->orderBy('name')->get();
        $desks = Desk::visibleTo($user)->orderBy('name')->get();

        return Inertia::render('settings/Macros', [
            'macros' => MacroData::collect($macros),
            'desks' => DeskData::collect($desks),
            'editableIds' => $macros->filter(fn (Macro $macro): bool => Gate::forUser($user)->allows('update', $macro))->pluck('id')->values(),
            'canCreateWorkspaceMacro' => Gate::forUser($user)->allows('create', new Macro),
            'creatableDeskIds' => $desks->filter(fn (Desk $desk): bool => Gate::forUser($user)->allows('create', new Macro(['desk_id' => $desk->getKey()])))->pluck('id')->values(),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    public function store(Request $request, CreateMacro $createMacro): RedirectResponse
    {
        $createMacro->handle($request->user(), $request->all());

        Flash::success(__('Macro created.'));

        return back();
    }

    public function update(Request $request, Macro $macro, UpdateMacro $updateMacro): RedirectResponse
    {
        $updateMacro->handle($request->user(), $macro, $request->all());

        Flash::success(__('Macro updated.'));

        return back();
    }

    public function destroy(Request $request, Macro $macro, DeleteMacro $deleteMacro): RedirectResponse
    {
        $deleteMacro->handle($request->user(), $macro);

        Flash::success(__('Macro deleted.'));

        return back();
    }
}
