<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\SetTicketReminder;
use App\Data\Tickets\SetTicketReminderData;
use App\Data\Tickets\TicketReminderData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Remind yourself (or, with `for_assignees`, whoever is assigned) about a ticket at a future time.')]
class SetReminderTool extends Tool
{
    use ResolvesTickets;

    public function handle(Request $request, SetTicketReminder $setTicketReminder): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');
        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        return Response::json(TicketReminderData::from($setTicketReminder->handle($user, $ticket, $request->except(['ticket']))));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('Ticket number (123 or #123) or `tkt_…` id.')->required(),
            ...DataSchema::for(SetTicketReminderData::class),
        ];
    }
}
