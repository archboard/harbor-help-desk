<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Desks\RestoreDesk;
use App\Actions\Desks\UpdateDesk;
use App\Data\Desks\DeskData;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class DeskController extends Controller
{
    /**
     * The desks the user can see in the current workspace.
     *
     * @return PaginatedDataCollection<int, DeskData>
     */
    public function index(Request $request): PaginatedDataCollection
    {
        Gate::authorize('viewAny', Desk::class);

        return DeskData::collect(
            Desk::visibleTo($request->user())->with('customFields')->orderBy('name')->paginate(),
            PaginatedDataCollection::class,
        );
    }

    public function store(Request $request, CreateDesk $createDesk): DeskData
    {
        return DeskData::from($createDesk->handle($request->user(), $request->all()));
    }

    public function show(Desk $desk): DeskData
    {
        Gate::authorize('view', $desk);

        return DeskData::from($desk->load('customFields'));
    }

    public function update(Request $request, Desk $desk, UpdateDesk $updateDesk): DeskData
    {
        return DeskData::from($updateDesk->handle($request->user(), $desk, $request->all()));
    }

    /**
     * Archive the desk.
     */
    public function destroy(Request $request, Desk $desk, ArchiveDesk $archiveDesk): Response
    {
        $archiveDesk->handle($request->user(), $desk);

        return response()->noContent();
    }

    public function restore(Request $request, Desk $desk, RestoreDesk $restoreDesk): JsonResponse
    {
        return response()->json(DeskData::from($restoreDesk->handle($request->user(), $desk)));
    }
}
