<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\DeleteTicketAttachment;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        return AttachmentResource::collection($ticket->attachments()->paginate(100));
    }

    /**
     * Attach the multipart `file` to the ticket.
     */
    public function store(Request $request, Ticket $ticket, AddTicketAttachment $addTicketAttachment): AttachmentResource
    {
        return new AttachmentResource($addTicketAttachment->handle($request->user(), $ticket, $request->all()));
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
