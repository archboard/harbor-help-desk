<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Data\Tickets\TicketCommentData;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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
    public function store(Request $request, #[CurrentUser] User $user, Ticket $ticket, AddTicketComment $addTicketComment): TicketCommentData
    {
        return TicketCommentData::from($addTicketComment->handle($user, $ticket, $request->all())->load(['author', 'attachments']));
    }

    public function update(Request $request, #[CurrentUser] User $user, TicketComment $comment, UpdateTicketComment $updateTicketComment): TicketCommentData
    {
        return TicketCommentData::from($updateTicketComment->handle($user, $comment, $request->all())->load(['author', 'attachments']));
    }

    public function destroy(#[CurrentUser] User $user, TicketComment $comment, DeleteTicketComment $deleteTicketComment): Response
    {
        $deleteTicketComment->handle($user, $comment);

        return response()->noContent();
    }
}
