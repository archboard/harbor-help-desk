<?php

namespace App\Mcp\Prompts;

use App\Actions\Tickets\SearchTickets;
use App\Data\Tickets\TicketData;
use App\Models\Desk;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Review open, unassigned tickets and suggest a priority, tags and assignee for each. Nothing is changed until you confirm.')]
class TriageQueuePrompt extends Prompt
{
    public function handle(Request $request, SearchTickets $searchTickets): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = ['status' => 'open', 'assignee_id' => 'none', 'sort' => 'created'];
        $deskReference = (string) $request->get('desk');

        if ($deskReference !== '') {
            $desk = Desk::visibleTo($user)->whereKey($deskReference)->first();

            if ($desk === null) {
                return Response::error("Desk {$deskReference} not found.");
            }

            $filters['desk_id'] = $desk->id;
        }

        $tickets = $searchTickets->handle($user, $filters)->with(['requester', 'tags'])->limit(25)->get();
        $json = json_encode(TicketData::collect($tickets), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Response::text(<<<TEXT
        Open, unassigned tickets, oldest first (up to 25):

        {$json}

        For each ticket, suggest a priority, tags and an assignee. Call `describe_workspace` for the valid priorities, existing tags and desk members, and `get_ticket` when the subject is not enough to decide. If there are more than 25, call `search_tickets` with status open and assignee_id none for the rest.

        Present the suggestions as a table with a one-line reason each, then ask which to apply. Change nothing until the user confirms. Then call `update_ticket`, `set_ticket_tags` and `set_ticket_assignees`, or `bulk_update_tickets` when many tickets get the same change.
        TEXT);
    }

    /**
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('desk', 'Only triage this desk (`dsk_…` id). Defaults to every desk you belong to.'),
        ];
    }
}
