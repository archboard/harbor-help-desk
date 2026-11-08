<?php

namespace App\Mcp\Tools;

use App\Actions\Macros\ApplyMacro;
use App\Data\Macros\AppliedMacroData;
use App\Mcp\Tools\Concerns\ResolvesTickets;
use App\Models\Macro;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Run a macro\'s actions (status, priority, tags, assign to you) on a ticket and return its rendered reply text and whether it is an internal note. Nothing is commented or emailed: send the text with reply_to_ticket.')]
class ApplyMacroTool extends Tool
{
    use ResolvesTickets;

    public function handle(Request $request, ApplyMacro $applyMacro): Response
    {
        /** @var User $user */
        $user = $request->user();
        $reference = (string) $request->get('ticket');
        $ticket = $this->resolveTicket($user, $reference);

        if ($ticket === null) {
            return Response::error("Ticket {$reference} not found.");
        }

        $macro = Macro::find((string) $request->get('macro'));

        if ($macro === null) {
            return Response::error("Macro {$request->get('macro')} not found.");
        }

        $applied = $applyMacro->handle($user, $ticket, $macro);

        return Response::json(new AppliedMacroData($applied['body'], $applied['is_internal']));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'ticket' => $schema->string()->description('Ticket number (123 or #123) or `tkt_…` id.')->required(),
            'macro' => $schema->string()->description('The `mac_…` id of the macro, from describe_workspace.')->required(),
        ];
    }
}
