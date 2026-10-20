<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Data\Tickets\TicketCommentData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\PaginatedDataCollection;

class TicketCommentController extends Controller
{
    /**
     * The ticket's conversation, oldest first.
     *
     * @return PaginatedDataCollection<int, TicketCommentData>
     */
    public function index(Ticket $ticket): PaginatedDataCollection
    {
        Gate::authorize('view', $ticket);

        return TicketCommentData::collect($ticket->comments()->with(['author', 'attachments'])->paginate(100), PaginatedDataCollection::class);
    }

    /**
     * Reply or add an internal note. Send as multipart to include `attachments[]`.
     */
    public function store(Request $request, Ticket $ticket, AddTicketComment $addTicketComment): TicketCommentData
    {
        return TicketCommentData::from($addTicketComment->handle($request->user(), $ticket, $request->all())->load(['author', 'attachments']));
    }

    public function update(Request $request, TicketComment $comment, UpdateTicketComment $updateTicketComment): TicketCommentData
    {
        return TicketCommentData::from($updateTicketComment->handle($request->user(), $comment, $request->all())->load(['author', 'attachments']));
    }

    public function destroy(Request $request, TicketComment $comment, DeleteTicketComment $deleteTicketComment): Response
    {
        $deleteTicketComment->handle($request->user(), $comment);

        return response()->noContent();
    }
}
