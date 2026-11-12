<?php

namespace App\Mcp\Tools;

use App\Actions\Workspaces\RemoveMember;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remove a member from the workspace. Their open tickets are unassigned, and their desk memberships and workspace API tokens are dropped. Only owners remove owners, and the last owner can\'t go. Owners and admins only.')]
class RemoveMemberTool extends Tool
{
    public function handle(Request $request, RemoveMember $removeMember): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = (string) $request->get('member');
        $member = User::find($id);

        if ($member === null) {
            return Response::error("Member {$id} not found.");
        }

        try {
            $removeMember->handle($user, $member);
        } catch (ModelNotFoundException) {
            return Response::error("Member {$id} not found.");
        }

        return Response::json(['removed' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'member' => $schema->string()->description('Member `usr_…` id.')->required(),
        ];
    }
}
