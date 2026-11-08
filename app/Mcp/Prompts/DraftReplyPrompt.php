<?php

namespace App\Mcp\Prompts;

use App\Mcp\Prompts\Concerns\RendersTickets;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;

#[Description('Draft a reply to the customer on a ticket, then offer to post it as an internal note or a reply.')]
class DraftReplyPrompt extends Prompt
{
    use RendersTickets;

    public function handle(Request $request): Response
    {
        return $this->renderTicketPrompt($request, <<<'TEXT'
        Draft the next reply to the customer. Answer what they asked, match their tone, and use the internal notes for context without quoting them.

        If a macro could fit, check `describe_workspace` for the macros, and offer `apply_macro`.

        Show the draft and stop. Then offer two options: post it as an internal note, or send it as a public reply. Post nothing until the user chooses. To post, call `reply_to_ticket` with `ticket`, `body`, `is_internal` (true for a note, false for a reply, which is emailed to the customer at once and cannot be unsent) and `last_seen_comment_id` set to the id of the last comment above.
        TEXT);
    }
}
