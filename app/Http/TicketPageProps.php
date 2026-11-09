<?php

namespace App\Http;

use App\Data\Desks\DeskData;
use App\Data\Desks\DeskMemberData;
use App\Data\Macros\MacroData;
use App\Data\Tags\TagData;
use App\Data\Tickets\AttachmentData;
use App\Data\Tickets\TicketCommentData;
use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketEventData;
use App\Data\Tickets\TicketReminderData;
use App\Enums\TicketEventType;
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
 */
class TicketPageProps
{
    /**
     * Events that the thread already shows as comments.
     */
    protected const array EVENTS_SHOWN_AS_COMMENTS = [
        TicketEventType::COMMENT_ADDED,
        TicketEventType::INTERNAL_NOTE_ADDED,
    ];

    /**
     * @param  bool  $deferMacros  Load the macros in a follow-up request (the page) or with the rest (the preview, which is a single prop).
     * @return array<string, mixed>
     */
    public static function handle(User $user, Ticket $ticket, bool $deferMacros = false): array
    {
        $ticket->load(['desk.customFields', 'requester', 'assignees', 'tags', 'slaTargets', 'satisfactionRating']);

        $macros = fn () => MacroData::collect(Macro::visibleTo($user)->usableOn($ticket)->orderBy('name')->get());

        return [
            'ticket' => TicketData::from($ticket),
            'comments' => TicketCommentData::collect($ticket->comments()->with(['author', 'attachments'])->get()),
            'events' => TicketEventData::collect($ticket->events()
                ->with('actor')
                ->whereNotIn('type', self::EVENTS_SHOWN_AS_COMMENTS)
                ->get()),
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
