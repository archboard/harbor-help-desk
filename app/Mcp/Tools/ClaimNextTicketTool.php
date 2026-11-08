<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\ClaimNextTicket;
use App\Data\Tickets\TicketData;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Assign yourself the next waiting ticket: open, unassigned, on your desks, urgent first, then oldest. Says so when the queue is empty.')]
class ClaimNextTicketTool extends Tool
{
    public function handle(Request $request, ClaimNextTicket $claimNextTicket): Response
    {
        /** @var User $user */
        $user = $request->user();
        $ticket = $claimNextTicket->handle($user);

        if ($ticket === null) {
            return Response::text('No tickets are waiting.');
        }

        return Response::json(TicketData::from($ticket->load(['desk', 'requester', 'assignees', 'tags', 'slaTargets'])));
    }
}
