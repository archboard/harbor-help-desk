<?php

namespace App\Mcp\Tools;

use App\Data\Tickets\TicketCommentData;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketEventData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get one ticket by number or `tkt_…` id: its fields, SLA targets, the whole conversation (internal notes have is_internal true; attachments list metadata only) and the last 20 events.')]
#[IsReadOnly]
class GetTicketTool extends Tool
{
    use ResolvesTickets;

    public function handle(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');

        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        Gate::forUser($user)->authorize('view', $ticket);

        $events = $ticket->events()->with('actor')->orderByDesc('id')->limit(20)->get()->reverse()->values();

        return Response::json([
            'ticket' => TicketData::from($ticket->load(['desk', 'requester', 'assignees', 'tags', 'slaTargets', 'satisfactionRating'])),
            'comments' => TicketCommentData::collect($ticket->comments()->with(['author', 'attachments'])->get()),
            'events' => TicketEventData::collect($events),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('Ticket number (123 or #123) or `tkt_…` id.')->required(),
        ];
    }
}
