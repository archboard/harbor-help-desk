<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Macros\CreateMacro;
use App\Actions\Macros\DeleteMacro;
use App\Actions\Macros\UpdateMacro;
use App\Data\Macros\CreateMacroData;
use App\Data\Macros\MacroData;
use App\Data\Macros\UpdateMacroData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\Macro;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class MacroController extends Controller
{
    /**
     * The user's personal macros and the shared ones they can see.
     *
     * @return Collection<int, MacroData>
     */
    public function index(#[CurrentUser] User $user): Collection
    {
        Gate::authorize('viewAny', Macro::class);

        return MacroData::collect(Macro::visibleTo($user)->orderBy('name')->get());
    }

    #[ApiBody(CreateMacroData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateMacro $createMacro): MacroData
    {
        return MacroData::from($createMacro->handle($user, $request->all()));
    }

    public function show(Macro $macro): MacroData
    {
        Gate::authorize('view', $macro);

        return MacroData::from($macro);
    }

    #[ApiBody(UpdateMacroData::class)]
    public function update(Request $request, #[CurrentUser] User $user, Macro $macro, UpdateMacro $updateMacro): MacroData
    {
        return MacroData::from($updateMacro->handle($user, $macro, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, Macro $macro, DeleteMacro $deleteMacro): Response
    {
        $deleteMacro->handle($user, $macro);

        return response()->noContent();
    }
}
