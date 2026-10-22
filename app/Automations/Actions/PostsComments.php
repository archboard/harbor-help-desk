<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\AddTicketComment;
use App\Automations\Context;
use App\Enums\TicketEventType;
use App\Models\Automation;
use App\Models\Contact;

/**
 * Base for actions that write a note or a reply on the ticket through {@see AddTicketComment}.
 *
 * Phase 2 already drops inbound auto-replies and bounces before they become comments, so a reply
 * is refused ("Skipped: ...", never a failure) only when it could start a loop with what is left:
 * the triggering event is a bounce, the triggering comment was written by an automation (no
 * automation-to-automation chains), or the ticket's latest outbound reply bounced.
 */
abstract class PostsComments extends ConfiguredAction
{
    /**
     * Why a public reply must not be sent now, or null when it may.
     */
    protected function replyBlockedReason(Context $context): ?string
    {
        if ($context->event?->type === TicketEventType::EMAIL_BOUNCED) {
            return __('Skipped: the triggering event is a bounce.');
        }

        if ($context->comment()?->author_type === (new Automation)->getMorphClass()) {
            return __('Skipped: the triggering message was written by an automation.');
        }

        $lastOutbound = $context->ticket->comments()
            ->where('is_internal', false)
            ->where('author_type', '!=', (new Contact)->getMorphClass())
            ->reorder()->latest()->latest('id')->first();

        $bounced = $context->ticket->events()
            ->where('type', TicketEventType::EMAIL_BOUNCED)
            ->when($lastOutbound, fn ($query) => $query->where('created_at', '>=', $lastOutbound->created_at))
            ->exists();

        return $bounced ? __('Skipped: the last reply to the requester bounced.') : null;
    }

    /**
     * Post the body as a note, or as a public reply unless {@see self::replyBlockedReason()} says no.
     */
    protected function post(Context $context, string $body, bool $internal): string
    {
        if (! $internal && ($reason = $this->replyBlockedReason($context)) !== null) {
            return $reason;
        }

        app(AddTicketComment::class)->handle($this->actor($context), $context->ticket, ['body' => $body, 'is_internal' => $internal]);

        return $internal ? __('Added an internal note.') : __('Sent a reply.');
    }
}
