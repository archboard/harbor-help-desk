<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\SyncTicketTags;
use App\Http\Flash;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketTagController extends Controller
{
    /**
     * Replace the ticket's tags with `names`, creating tags that don't exist yet.
     */
    public function update(Request $request, #[CurrentUser] User $user, Ticket $ticket, SyncTicketTags $syncTicketTags): RedirectResponse
    {
        $syncTicketTags->handle($user, $ticket, $request->all());

        Flash::success(__('Tags updated.'));

        return back();
    }
}
