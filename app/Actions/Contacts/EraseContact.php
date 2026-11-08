<?php

namespace App\Actions\Contacts;

use App\Data\Contacts\EraseContactData;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EraseContact
{
    public const string REMOVED = '[removed]';

    /**
     * Irreversibly scrub a contact's personal data. The contact row, their tickets, agent replies and
     * metrics stay, so reports still add up. Ticket events are append-only, so the few that carry the
     * contact's words are rewritten with the query builder, which skips the model's guard.
     * Portal sign-in links are signed and stateless, so none are stored; the email no longer matches any.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, Contact $contact, array $input): Contact
    {
        Gate::forUser($actor)->authorize('erase', $contact);

        EraseContactData::validateAndCreateWith($input, [
            'confirm_email' => [fn (string $attribute, mixed $value, callable $fail) => strcasecmp((string) $value, $contact->email) === 0 || $fail(__('The email does not match this contact.'))],
        ]);

        $files = DB::transaction(function () use ($actor, $contact): array {
            $commentIds = TicketComment::withTrashed()->where('author_type', $contact->getMorphClass())->where('author_id', $contact->getKey())->pluck('id');
            $attachments = Attachment::query()
                ->where(fn ($query) => $query->where('uploader_type', $contact->getMorphClass())->where('uploader_id', $contact->getKey())->orWhereIn('ticket_comment_id', $commentIds))
                ->get();

            TicketComment::withTrashed()->whereIn('id', $commentIds)->update(['body' => self::REMOVED, 'message_id' => null]);
            SatisfactionRating::query()->where('contact_id', $contact->getKey())->update(['comment' => null]);
            Attachment::query()->whereIn('id', $attachments->modelKeys())->delete();

            $this->scrubTickets($contact, $attachments->modelKeys(), $commentIds->all());

            $contact->notifications()->delete();
            $contact->update(['name' => __('Erased contact'), 'email' => "erased+{$contact->getKey()}@invalid"]);

            AuditEvent::record(AuditAction::CONTACT_ERASED, $actor, $contact);

            return $attachments->map(fn (Attachment $attachment): array => [$attachment->disk, $attachment->path])->all();
        });

        foreach ($files as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $contact;
    }

    /**
     * Rename the contact's tickets and blank the old subjects, quoted comment bodies and filenames in their events.
     * Tickets are saved as models but record no event, since one would hold the new subject for nothing.
     *
     * @param  array<int, string>  $attachmentIds
     * @param  array<int, string>  $commentIds
     */
    private function scrubTickets(Contact $contact, array $attachmentIds, array $commentIds): void
    {
        foreach (Ticket::withTrashed()->where('requester_id', $contact->getKey())->cursor() as $ticket) {
            $ticket->update(['subject' => __('Ticket #:number', ['number' => $ticket->number])]);

            $events = TicketEvent::query()->where('ticket_id', $ticket->getKey())->whereIn('type', [
                TicketEventType::SUBJECT_CHANGED, TicketEventType::COMMENT_EDITED, TicketEventType::ATTACHMENT_ADDED, TicketEventType::ATTACHMENT_REMOVED,
            ])->get();

            foreach ($events as $event) {
                $properties = $event->properties;

                $scrubbed = match ($event->type) {
                    TicketEventType::SUBJECT_CHANGED => ['from' => self::REMOVED, 'to' => $ticket->subject] + $properties,
                    TicketEventType::COMMENT_EDITED => in_array($properties['comment_id'] ?? null, $commentIds, true) ? ['from' => self::REMOVED] + $properties : null,
                    default => in_array($properties['attachment_id'] ?? null, $attachmentIds, true) ? ['filename' => self::REMOVED] + $properties : null,
                };

                if ($scrubbed !== null) {
                    TicketEvent::query()->whereKey($event->getKey())->update(['properties' => $scrubbed]);
                }
            }
        }
    }
}
