<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Http\Flash;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    /**
     * Reply to the ticket, or add an internal note, with any attached files.
     */
    public function store(Request $request, #[CurrentUser] User $user, Ticket $ticket, AddTicketComment $addTicketComment): RedirectResponse
    {
        $comment = $addTicketComment->handle($user, $ticket, $request->all());

        Flash::success($comment->is_internal ? __('Note added.') : __('Reply sent.'));

        return back();
    }

    public function update(Request $request, #[CurrentUser] User $user, TicketComment $comment, UpdateTicketComment $updateTicketComment): RedirectResponse
    {
        $updateTicketComment->handle($user, $comment, $request->all());

        Flash::success(__('Comment updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, TicketComment $comment, DeleteTicketComment $deleteTicketComment): RedirectResponse
    {
        $deleteTicketComment->handle($user, $comment);

        Flash::success(__('Comment deleted.'));

        return back();
    }
}
