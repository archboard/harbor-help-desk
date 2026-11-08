<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\CreateTicket;
use App\Data\Tickets\CreateTicketData;
use App\Data\Tickets\TicketData;
use App\Enums\TicketSource;
use App\Models\Desk;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Open a ticket on a desk. Give `requester_id` or `requester` (name and email). An optional `body` becomes the first public reply and is emailed to the requester, so leave it out for a draft.')]
class CreateTicketTool extends Tool
{
    public function handle(Request $request, CreateTicket $createTicket): Response
    {
        /** @var User $user */
        $user = $request->user();
        $desk = Desk::find((string) $request->get('desk_id'));

        if ($desk === null) {
            return Response::error("Desk {$request->get('desk_id')} not found.");
        }

        $ticket = $createTicket->handle($user, $desk, $request->except(['desk_id']), TicketSource::API);

        return Response::json(TicketData::from($ticket->load(['desk', 'requester', 'assignees', 'tags', 'slaTargets'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'desk_id' => $schema->string()->description('The `dsk_…` id of the desk to open the ticket on.')->required(),
            ...Arr::except(DataSchema::for(CreateTicketData::class), ['attachments', 'mailbox_id', 'message_id']),
        ];
    }
}
