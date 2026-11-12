<?php

namespace App\Mcp\Tools;

use App\Actions\Workspaces\ResendInvitation;
use App\Data\Workspaces\WorkspaceInvitationData;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Email a pending invitation again with a fresh 7-day link. Owners and admins only. Returns the invitation.')]
#[IsOpenWorld]
class ResendInvitationTool extends Tool
{
    public function handle(Request $request, ResendInvitation $resendInvitation): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = (string) $request->get('invitation');
        $invitation = WorkspaceInvitation::find($id);

        if ($invitation === null) {
            return Response::error("Invitation {$id} not found.");
        }

        $resendInvitation->handle($user, $invitation);

        return Response::json(WorkspaceInvitationData::from($invitation->load(['desk', 'invitedBy'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invitation' => $schema->string()->description('Invitation `inv_…` id.')->required(),
        ];
    }
}
