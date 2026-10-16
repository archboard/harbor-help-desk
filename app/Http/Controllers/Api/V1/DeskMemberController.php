<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\RemoveDeskMember;
use App\Actions\Desks\SetDeskMember;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeskMemberResource;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DeskMemberController extends Controller
{
    public function index(Desk $desk): AnonymousResourceCollection
    {
        Gate::authorize('view', $desk);

        return DeskMemberResource::collection($desk->users()->orderBy('name')->paginate());
    }

    /**
     * Add the user to the desk, or change their role.
     */
    public function update(Request $request, Desk $desk, User $user, SetDeskMember $setDeskMember): Response
    {
        $setDeskMember->handle($request->user(), $desk, $user, $request->all());

        return response()->noContent();
    }

    public function destroy(Request $request, Desk $desk, User $user, RemoveDeskMember $removeDeskMember): Response
    {
        $removeDeskMember->handle($request->user(), $desk, $user);

        return response()->noContent();
    }
}
