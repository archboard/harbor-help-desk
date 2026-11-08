<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\SyncTicketTags;
use App\Data\Tickets\SyncTicketTagsData;
use App\Data\Tickets\TicketData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Replace the ticket\'s tags with `names`, creating tags that don\'t exist yet (an empty list clears them). Returns the updated ticket.')]
#[IsIdempotent]
class SetTicketTagsTool extends Tool
{
    use ResolvesTickets;

    public function handle(Request $request, SyncTicketTags $syncTicketTags): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');
        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        $syncTicketTags->handle($user, $ticket, $request->except(['ticket']));

        return Response::json(TicketData::from($ticket->load(['desk', 'requester', 'assignees', 'tags', 'slaTargets'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('Ticket number (123 or #123) or `tkt_…` id.')->required(),
            ...DataSchema::for(SyncTicketTagsData::class),
        ];
    }
}
