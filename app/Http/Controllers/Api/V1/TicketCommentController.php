<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Http\Controllers\Controller;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TicketCommentController extends Controller
{
    /**
     * The ticket's conversation, oldest first.
     */
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        Gate::authorize('view', $ticket);

        return TicketCommentResource::collection($ticket->comments()->with(['author', 'attachments'])->paginate(100));
    }

    /**
     * Reply or add an internal note. Send as multipart to include `attachments[]`.
     */
    public function store(Request $request, Ticket $ticket, AddTicketComment $addTicketComment): TicketCommentResource
    {
        return new TicketCommentResource($addTicketComment->handle($request->user(), $ticket, $request->all())->load(['author', 'attachments']));
    }

    public function update(Request $request, TicketComment $comment, UpdateTicketComment $updateTicketComment): TicketCommentResource
    {
        return new TicketCommentResource($updateTicketComment->handle($request->user(), $comment, $request->all())->load(['author', 'attachments']));
    }

    public function destroy(Request $request, TicketComment $comment, DeleteTicketComment $deleteTicketComment): Response
    {
        $deleteTicketComment->handle($request->user(), $comment);

        return response()->noContent();
    }
}
