<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\MoveTicket;
use App\Data\Tickets\TicketData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Move a ticket to another desk. You need access to both desks.')]
#[IsIdempotent]
class MoveTicketTool extends Tool
{
    use ResolvesTickets;

    public function handle(Request $request, MoveTicket $moveTicket): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');
        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        $desk = Desk::find((string) $request->get('desk_id'));

        if ($desk === null) {
            return Response::error("Desk {$request->get('desk_id')} not found.");
        }

        $ticket = $moveTicket->handle($user, $ticket, $desk);

        return Response::json(TicketData::from($ticket->load(['desk', 'requester', 'assignees', 'tags', 'slaTargets'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('Ticket number (123 or #123) or `tkt_…` id.')->required(),
            'desk_id' => $schema->string()->description('The `dsk_…` id of the desk to move the ticket to.')->required(),
        ];
    }
}
