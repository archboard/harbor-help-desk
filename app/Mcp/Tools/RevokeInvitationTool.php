<?php

namespace App\Mcp\Tools;

use App\Actions\Workspaces\RevokeInvitation;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Revoke a pending invitation. Its emailed link stops working. Owners and admins only.')]
class RevokeInvitationTool extends Tool
{
    public function handle(Request $request, RevokeInvitation $revokeInvitation): Response
    {
        /** @var User $user */
        $user = $request->user();
        $id = (string) $request->get('invitation');
        $invitation = WorkspaceInvitation::find($id);

        if ($invitation === null) {
            return Response::error("Invitation {$id} not found.");
        }

        $revokeInvitation->handle($user, $invitation);

        return Response::json(['revoked' => $id]);
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
