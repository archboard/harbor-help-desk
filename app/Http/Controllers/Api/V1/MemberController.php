<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\RemoveMember;
use App\Actions\Workspaces\UpdateMemberRole;
use App\Data\Workspaces\UpdateMemberRoleData;
use App\Data\Workspaces\WorkspaceMemberData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class MemberController extends Controller
{
    /**
     * The workspace's members with their role and desk count. Owners and admins only.
     *
     * @return PaginatedDataCollection<int, WorkspaceMemberData>
     */
    public function index(): PaginatedDataCollection
    {
        $workspace = Workspace::current();

        Gate::authorize('manageMembers', $workspace);

        return WorkspaceMemberData::collect($workspace->users()->withCount('desks')->orderBy('name')->orderBy('users.id')->paginate(), PaginatedDataCollection::class);
    }

    /**
     * Change a member's role.
     */
    #[ApiBody(UpdateMemberRoleData::class)]
    public function update(Request $request, #[CurrentUser] User $actor, User $member, UpdateMemberRole $updateMemberRole): WorkspaceMemberData
    {
        $updateMemberRole->handle($actor, $member, $request->all());

        return WorkspaceMemberData::from(Workspace::current()->users()->withCount('desks')->whereKey($member->getKey())->sole());
    }

    /**
     * Remove a member, or leave the workspace when it is yourself.
     */
    public function destroy(#[CurrentUser] User $actor, User $member, RemoveMember $removeMember): Response
    {
        $removeMember->handle($actor, $member);

        return response()->noContent();
    }
}
