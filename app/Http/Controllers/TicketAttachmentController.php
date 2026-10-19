<?php

namespace App\Http\Controllers;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\DeleteTicketAttachment;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentController extends Controller
{
    /**
     * Attach a file to the ticket itself rather than to a comment.
     */
    public function store(Request $request, Ticket $ticket, AddTicketAttachment $addTicketAttachment): RedirectResponse
    {
        $addTicketAttachment->handle($request->user(), $ticket, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File attached.')]);

        return back();
    }

    public function destroy(Request $request, Attachment $attachment, DeleteTicketAttachment $deleteTicketAttachment): RedirectResponse
    {
        $deleteTicketAttachment->handle($request->user(), $attachment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('File removed.')]);

        return back();
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $attachment->download();
    }
}
