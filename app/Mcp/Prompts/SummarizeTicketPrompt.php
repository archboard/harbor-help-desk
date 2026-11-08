<?php

namespace App\Mcp\Prompts;

use App\Mcp\Prompts\Concerns\RendersTickets;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;

#[Description('Summarize a ticket and its conversation, and suggest the next step.')]
class SummarizeTicketPrompt extends Prompt
{
    use RendersTickets;

    public function handle(Request $request): Response
    {
        return $this->renderTicketPrompt($request, <<<'TEXT'
        Summarize this ticket for an agent picking it up: who the customer is, what they need, what has been tried, the current status, and what should happen next. Keep it short. Mention internal notes only as internal context.

        If the conversation looks incomplete, call `get_ticket` for the latest events and SLA targets. Do not change the ticket unless asked.
        TEXT);
    }
}
