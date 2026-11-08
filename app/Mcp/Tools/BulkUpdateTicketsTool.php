<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\BulkUpdateTickets;
use App\Data\Tickets\BulkUpdateTicketsData;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Apply exactly one change (`status`, `priority`, `assignee_ids` which replaces the assignees, `add_tag` or `desk_id`) to up to 100 tickets, all or nothing. `ticket_ids` are `tkt_…` ids, not numbers. Returns how many changed.')]
class BulkUpdateTicketsTool extends Tool
{
    public function handle(Request $request, BulkUpdateTickets $bulkUpdateTickets): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Response::json(['updated' => $bulkUpdateTickets->handle($user, $request->except(['delete']))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return Arr::except(DataSchema::for(BulkUpdateTicketsData::class), ['delete']);
    }
}
