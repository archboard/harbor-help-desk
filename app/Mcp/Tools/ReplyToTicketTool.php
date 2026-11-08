<?php

namespace App\Mcp\Tools;

use App\Actions\Tickets\AddTicketComment;
use App\Data\Tickets\AddTicketCommentData;
use App\Data\Tickets\TicketCommentData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Description('Add a comment to a ticket. With `is_internal` true it is a note only agents see. Otherwise it is a public reply that is emailed to the customer straight away and cannot be unsent. Pass `last_seen_comment_id` (the newest comment id you read, null if none) so the reply is rejected when someone else answered meanwhile.')]
#[IsOpenWorld]
class ReplyToTicketTool extends Tool
{
    use ResolvesTickets;

    public function handle(Request $request, AddTicketComment $addTicketComment): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');
        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        $comment = $addTicketComment->handle($user, $ticket, $request->except(['ticket', 'attachments']));

        return Response::json(TicketCommentData::from($comment->load(['author', 'attachments'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('Ticket number (123 or #123) or `tkt_…` id.')->required(),
            ...Arr::except(DataSchema::for(AddTicketCommentData::class), ['attachments', 'message_id']),
        ];
    }
}
