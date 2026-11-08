<?php

namespace App\Mcp\Prompts\Concerns;

use App\Data\Tickets\TicketCommentData;
use App\Data\Tickets\TicketData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompts\Argument;

trait RendersTickets
{
    use ResolvesTickets;

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('ticket', 'Ticket number (123 or #123) or `tkt_…` id.', required: true),
        ];
    }

    /**
     * The prompt text for the ticket in the request: the ticket and its conversation as
     * JSON (internal notes have is_internal true), followed by the instructions.
     */
    protected function renderTicketPrompt(Request $request, string $instructions): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');

        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        $ticket->load(['desk', 'requester', 'assignees', 'tags']);

        $ticketJson = json_encode(TicketData::from($ticket), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $commentsJson = json_encode(
            TicketCommentData::collect($ticket->comments()->with(['author'])->get()),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return Response::text(<<<TEXT
        Ticket #{$ticket->number} ({$ticket->id}):

        {$ticketJson}

        Conversation, oldest first (is_internal true marks internal notes, which the customer never sees):

        {$commentsJson}

        {$instructions}
        TEXT);
    }
}
