<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\DeleteTicketAttachment;
use App\Http\Flash;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    /**
     * Attach a file to the ticket itself rather than to a comment.
     */
    public function store(Request $request, #[CurrentUser] User $user, Ticket $ticket, AddTicketAttachment $addTicketAttachment): RedirectResponse
    {
        $addTicketAttachment->handle($user, $ticket, $request->all());

        Flash::success(__('File attached.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, Attachment $attachment, DeleteTicketAttachment $deleteTicketAttachment): RedirectResponse
    {
        $deleteTicketAttachment->handle($user, $attachment);

        Flash::success(__('File removed.'));

        return back();
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $attachment->download();
    }
}
