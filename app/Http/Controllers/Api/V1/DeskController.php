<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Desks\RestoreDesk;
use App\Actions\Desks\UpdateDesk;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeskResource;
use App\Models\Desk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeskController extends Controller
{
    /**
     * The desks the user can see in the current workspace.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Desk::class);

        return DeskResource::collection(
            Desk::visibleTo($request->user())->orderBy('name')->paginate(),
        );
    }

    public function store(Request $request, CreateDesk $createDesk): DeskResource
    {
        return new DeskResource($createDesk->handle($request->user(), $request->all()));
    }

    public function show(Desk $desk): DeskResource
    {
        Gate::authorize('view', $desk);

        return new DeskResource($desk);
    }

    public function update(Request $request, Desk $desk, UpdateDesk $updateDesk): DeskResource
    {
        return new DeskResource($updateDesk->handle($request->user(), $desk, $request->all()));
    }

    /**
     * Archive the desk.
     */
    public function destroy(Request $request, Desk $desk, ArchiveDesk $archiveDesk): Response
    {
        $archiveDesk->handle($request->user(), $desk);

        return response()->noContent();
    }

    public function restore(Request $request, Desk $desk, RestoreDesk $restoreDesk): DeskResource
    {
        return new DeskResource($restoreDesk->handle($request->user(), $desk));
    }
}
