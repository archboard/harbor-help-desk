<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\ClaimNextTicket;
use App\Data\Tickets\TicketData;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TicketClaimController extends Controller
{
    public function __invoke(Request $request, ClaimNextTicket $claimNextTicket): JsonResponse|Response
    {
        $ticket = $claimNextTicket->handle($request->user());

        if ($ticket === null) {
            return response()->noContent();
        }

        return response()->json(TicketData::from($ticket->load(['requester', 'assignees', 'tags'])));
    }
}
