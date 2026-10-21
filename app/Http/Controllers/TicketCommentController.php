<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Http\Flash;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketCommentController extends Controller
{
    /**
     * Reply to the ticket, or add an internal note, with any attached files.
     */
    public function store(Request $request, Ticket $ticket, AddTicketComment $addTicketComment): RedirectResponse
    {
        $comment = $addTicketComment->handle($request->user(), $ticket, $request->all());

        Flash::success($comment->is_internal ? __('Note added.') : __('Reply sent.'));

        return back();
    }

    public function update(Request $request, TicketComment $comment, UpdateTicketComment $updateTicketComment): RedirectResponse
    {
        $updateTicketComment->handle($request->user(), $comment, $request->all());

        Flash::success(__('Comment updated.'));

        return back();
    }

    public function destroy(Request $request, TicketComment $comment, DeleteTicketComment $deleteTicketComment): RedirectResponse
    {
        $deleteTicketComment->handle($request->user(), $comment);

        Flash::success(__('Comment deleted.'));

        return back();
    }
}
