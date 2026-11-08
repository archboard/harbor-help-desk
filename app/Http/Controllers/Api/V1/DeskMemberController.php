<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\RemoveDeskMember;
use App\Actions\Desks\SetDeskMember;
use App\Data\Desks\DeskMemberData;
use App\Data\Desks\SetDeskMemberData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class DeskMemberController extends Controller
{
    /**
     * @return PaginatedDataCollection<int, DeskMemberData>
     */
    public function index(Desk $desk): PaginatedDataCollection
    {
        Gate::authorize('view', $desk);

        return DeskMemberData::collect($desk->users()->orderBy('name')->paginate(), PaginatedDataCollection::class);
    }

    /**
     * Add the user to the desk, or change their role.
     */
    #[ApiBody(SetDeskMemberData::class)]
    public function update(Request $request, #[CurrentUser] User $actor, Desk $desk, User $user, SetDeskMember $setDeskMember): Response
    {
        $setDeskMember->handle($actor, $desk, $user, $request->all());

        return response()->noContent();
    }

    public function destroy(#[CurrentUser] User $actor, Desk $desk, User $user, RemoveDeskMember $removeDeskMember): Response
    {
        $removeDeskMember->handle($actor, $desk, $user);

        return response()->noContent();
    }
}
