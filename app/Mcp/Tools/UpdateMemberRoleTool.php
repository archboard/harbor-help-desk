<?php

namespace App\Mcp\Tools;

use App\Actions\Workspaces\UpdateMemberRole;
use App\Data\Workspaces\UpdateMemberRoleData;
use App\Data\Workspaces\WorkspaceMemberData;
use App\Models\User;
use App\Models\Workspace;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Change a workspace member\'s role. Admins can set admin or agent; only owners can grant owner or change an owner. Owners and admins only. Returns the member.')]
#[IsIdempotent]
class UpdateMemberRoleTool extends Tool
{
    public function handle(Request $request, UpdateMemberRole $updateMemberRole): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = (string) $request->get('member');
        $member = User::find($id);

        if ($member === null) {
            return Response::error("Member {$id} not found.");
        }

        try {
            $updateMemberRole->handle($user, $member, $request->except('member'));
        } catch (ModelNotFoundException) {
            return Response::error("Member {$id} not found.");
        }

        // Reloaded directly: an admin who just demoted themselves can no longer list members.
        return Response::json(WorkspaceMemberData::from(Workspace::current()->users()->withCount('desks')->whereKey($member->getKey())->sole()));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->description('Member `usr_…` id.')->required(),
            ...DataSchema::for(UpdateMemberRoleData::class),
        ];
    }
}
