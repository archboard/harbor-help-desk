<?php

namespace App\Mcp\Tools;

use App\Actions\Workspaces\InviteMember;
use App\Data\Workspaces\InviteMemberData;
use App\Data\Workspaces\WorkspaceInvitationData;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Invite an email address to the workspace as an admin or agent, optionally to a desk. The invitation is emailed to them straight away. Owners and admins only. Returns the invitation.')]
#[IsOpenWorld]
class InviteMemberTool extends Tool
{
    public function handle(Request $request, InviteMember $inviteMember): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Response::json(WorkspaceInvitationData::from($inviteMember->handle($user, $request->all())->load(['desk', 'invitedBy'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return DataSchema::for(InviteMemberData::class);
    }
}
