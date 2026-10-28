<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\ArchiveDesk;
use App\Actions\Desks\CreateDesk;
use App\Actions\Desks\RestoreDesk;
use App\Actions\Desks\UpdateDesk;
use App\Data\Desks\DeskData;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function index(#[CurrentUser] User $user): PaginatedDataCollection
    {
        Gate::authorize('viewAny', Desk::class);

        return DeskData::collect(
            Desk::visibleTo($user)->with('customFields')->orderBy('name')->paginate(),
            PaginatedDataCollection::class,
        );
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateDesk $createDesk): DeskData
    {
        return DeskData::from($createDesk->handle($user, $request->all()));
    }

    public function show(Desk $desk): DeskData
    {
        Gate::authorize('view', $desk);

        return DeskData::from($desk->load('customFields'));
    }

    public function update(Request $request, #[CurrentUser] User $user, Desk $desk, UpdateDesk $updateDesk): DeskData
    {
        return DeskData::from($updateDesk->handle($user, $desk, $request->all()));
    }

    /**
     * Archive the desk.
     */
    public function destroy(#[CurrentUser] User $user, Desk $desk, ArchiveDesk $archiveDesk): Response
    {
        $archiveDesk->handle($user, $desk);

        return response()->noContent();
    }

    public function restore(#[CurrentUser] User $user, Desk $desk, RestoreDesk $restoreDesk): JsonResponse
    {
        return response()->json(DeskData::from($restoreDesk->handle($user, $desk)));
    }
}
