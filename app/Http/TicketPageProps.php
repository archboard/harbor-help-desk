<?php

namespace App\Http;

use App\Actions\Tickets\PageTicketThread;
use App\Data\Desks\DeskData;
use App\Data\Desks\DeskMemberData;
use App\Data\Macros\MacroData;
use App\Data\Tags\TagData;
use App\Data\Tickets\AttachmentData;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketReminderData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\User;
use Inertia\Inertia;

/**
 * The props of the full ticket view: the ticket page itself and the quick view's `preview` shared prop.
 * The thread starts with its newest page; the earlier ones load through `tickets.thread`.
 */
class TicketPageProps
{
    /**
     * @param  bool  $deferMacros  Load the macros in a follow-up request (the page) or with the rest (the preview, which is a single prop).
     * @return array<string, mixed>
     */
    public static function handle(User $user, Ticket $ticket, bool $deferMacros = false): array
    {
        $ticket->load(['desk.customFields', 'requester', 'assignees', 'tags', 'slaTargets', 'satisfactionRating']);

        $thread = app(PageTicketThread::class)->handle($ticket, []);

        $macros = fn () => MacroData::collect(Macro::visibleTo($user)->usableOn($ticket)->orderBy('name')->get());

        return [
            'ticket' => TicketData::from($ticket),
            'comments' => $thread->comments,
            'events' => $thread->events,
            'earlier_before' => $thread->earlierBefore,
            'attachments' => AttachmentData::collect($ticket->attachments()->whereNull('ticket_comment_id')->oldest()->get()),
            'reminders' => TicketReminderData::collect($ticket->reminders()
                ->visibleTo($user)
                ->whereNull('sent_at')
                ->orderBy('remind_at')
                ->get()),
            'deskMembers' => DeskMemberData::collect($ticket->desk->users()->orderBy('name')->get()),
            'desks' => DeskData::collect(Desk::visibleTo($user)->orderBy('name')->get()),
            'tags' => TagData::collect(Tag::query()->orderBy('name')->get()),
            'macros' => $deferMacros ? Inertia::defer($macros) : $macros(),
            'statuses' => TicketStatus::options(),
            'priorities' => TicketPriority::options(),
            'can' => [
                'update' => $user->can('update', $ticket),
                'delete' => $user->can('delete', $ticket),
                'restore' => $user->can('restore', $ticket),
            ],
        ];
    }
}
