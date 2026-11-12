<?php

namespace App\Mcp\Tools;

use App\Actions\Workspaces\ListWorkspaceMembers;
use App\Data\Workspaces\WorkspaceMemberData;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the workspace\'s members with their role and desk count, 25 per page. Owners and admins only. Use `page` to continue while `next_page` is set.')]
#[IsReadOnly]
class ListMembersTool extends Tool
{
    public function handle(Request $request, ListWorkspaceMembers $listWorkspaceMembers): Response
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $listWorkspaceMembers->handle($user)->paginate(25, page: max(1, (int) $request->get('page', 1)));

        return Response::json([
            'members' => WorkspaceMemberData::collect($paginator->items()),
            'page' => $paginator->currentPage(),
            'next_page' => $paginator->hasMorePages() ? $paginator->currentPage() + 1 : null,
            'total' => $paginator->total(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1.'),
        ];
    }
}
