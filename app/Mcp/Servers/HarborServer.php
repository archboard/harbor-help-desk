<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DescribeWorkspaceTool;
use App\Mcp\Tools\GetContactTool;
use App\Mcp\Tools\GetTicketStatsTool;
use App\Mcp\Tools\GetTicketTool;
use App\Mcp\Tools\SearchContactsTool;
use App\Mcp\Tools\SearchTicketsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Harbor Help')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
Harbor Help is a helpdesk. You act as the signed-in agent, within the desks they belong to.

Start with describe_workspace to learn the desks, custom fields, tags, macros, SLA policies, statuses and priorities.

Tickets are identified by a number (123 or #123, shown to people) or by a `tkt_…` id. Either works wherever a ticket is expected.

Ticket statuses are open, pending, on_hold, solved and closed. Pending and on_hold pause the SLA clock.

Comments are either internal notes or public replies. Internal notes are only seen by agents. A public reply is emailed to the customer straight away and cannot be unsent, so only send one when the user asked for it, and prefer an internal note for drafts.
TEXT)]
class HarborServer extends Server
{
    protected array $tools = [
        DescribeWorkspaceTool::class,
        SearchTicketsTool::class,
        GetTicketTool::class,
        SearchContactsTool::class,
        GetContactTool::class,
        GetTicketStatsTool::class,
    ];
}
