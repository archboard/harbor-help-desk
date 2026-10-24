<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Tickets\AddTicketComment;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketCommentController extends Controller
{
    /**
     * Reply to the contact's own ticket, with any attached files.
     */
    public function store(Request $request, string $ticket, AddTicketComment $addTicketComment): RedirectResponse
    {
        $contact = Auth::guard('contact')->user();

        $addTicketComment->handle($contact, $contact->tickets()->findOrFail($ticket), $request->only(['body', 'attachments']));

        Flash::success(__('Reply sent.'));

        return back();
    }
}
