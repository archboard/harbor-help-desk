<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\ClaimNextTicket;
use App\Data\Tickets\TicketData;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TicketClaimController extends Controller
{
    #[ApiBody]
    #[ApiResponse(TicketData::class)]
    public function __invoke(#[CurrentUser] User $user, ClaimNextTicket $claimNextTicket): JsonResponse|Response
    {
        $ticket = $claimNextTicket->handle($user);

        if ($ticket === null) {
            return response()->noContent();
        }

        return response()->json(TicketData::from($ticket->load(['requester', 'assignees', 'tags'])));
    }
}
