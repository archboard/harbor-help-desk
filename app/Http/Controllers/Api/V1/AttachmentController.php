<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\DeleteTicketAttachment;
use App\Data\Tickets\AttachmentData;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * @return PaginatedDataCollection<int, AttachmentData>
     */
    public function index(Ticket $ticket): PaginatedDataCollection
    {
        Gate::authorize('view', $ticket);

        return AttachmentData::collect($ticket->attachments()->paginate(100), PaginatedDataCollection::class);
    }

    /**
     * Attach the multipart `file` to the ticket.
     */
    public function store(Request $request, Ticket $ticket, AddTicketAttachment $addTicketAttachment): AttachmentData
    {
        return AttachmentData::from($addTicketAttachment->handle($request->user(), $ticket, $request->all()));
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return $attachment->download();
    }

    public function destroy(Request $request, Attachment $attachment, DeleteTicketAttachment $deleteTicketAttachment): Response
    {
        $deleteTicketAttachment->handle($request->user(), $attachment);

        return response()->noContent();
    }
}
