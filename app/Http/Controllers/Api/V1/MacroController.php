<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Macros\CreateMacro;
use App\Actions\Macros\DeleteMacro;
use App\Actions\Macros\UpdateMacro;
use App\Data\Macros\MacroData;
use App\Http\Controllers\Controller;
use App\Models\Macro;
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
    public function index(Request $request): Collection
    {
        Gate::authorize('viewAny', Macro::class);

        return MacroData::collect(Macro::visibleTo($request->user())->orderBy('name')->get());
    }

    public function store(Request $request, CreateMacro $createMacro): MacroData
    {
        return MacroData::from($createMacro->handle($request->user(), $request->all()));
    }

    public function show(Macro $macro): MacroData
    {
        Gate::authorize('view', $macro);

        return MacroData::from($macro);
    }

    public function update(Request $request, Macro $macro, UpdateMacro $updateMacro): MacroData
    {
        return MacroData::from($updateMacro->handle($request->user(), $macro, $request->all()));
    }

    public function destroy(Request $request, Macro $macro, DeleteMacro $deleteMacro): Response
    {
        $deleteMacro->handle($request->user(), $macro);

        return response()->noContent();
    }
}
