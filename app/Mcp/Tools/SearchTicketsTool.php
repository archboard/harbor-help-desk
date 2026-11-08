<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\SearchTickets;
use App\Data\Tickets\SearchTicketsData;
use App\Data\Tickets\TicketData;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Search the tickets on the desks the agent belongs to, 25 per page. Use `page` to continue while `next_page` is set.')]
#[IsReadOnly]
class SearchTicketsTool extends Tool
{
    public function handle(Request $request, SearchTickets $searchTickets): Response
    {
        /** @var User $user */
        $user = $request->user();

        $paginator = $searchTickets->handle($user, $request->except('page'))
            ->with(['requester', 'assignees', 'tags', 'slaTargets'])
            ->paginate(25, page: max(1, (int) $request->get('page', 1)));

        return Response::json([
            'tickets' => TicketData::collect($paginator->items()),
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
            ...DataSchema::for(SearchTicketsData::class),
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1.'),
        ];
    }
}
