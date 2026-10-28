<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\ClaimNextTicket;
use App\Http\Flash;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class TicketClaimController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ClaimNextTicket $claimNextTicket): RedirectResponse
    {
        $ticket = $claimNextTicket->handle($user);

        if ($ticket === null) {
            Flash::info(__('Nothing waiting.'));

            return back();
        }

        return to_route('tickets.show', $ticket);
    }
}
