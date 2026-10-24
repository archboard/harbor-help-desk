<?php

namespace App\Data\Tickets;

use App\Data\Contacts\ContactData;
use App\Data\Desks\DeskData;
use App\Data\Tags\TagData;
use App\Data\Users\UserData;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use stdClass;

class TicketData extends Data
{
    /**
     * @param  stdClass  $customFields  Values keyed by custom field id, as an object even when empty.
     * @param  Collection<int, UserData>|Lazy  $assignees
     * @param  Collection<int, TagData>|Lazy  $tags
     * @param  Collection<int, TicketSlaTargetData>|Lazy  $slaTargets
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $subject,
        public TicketStatus $status,
        public string $statusLabel,
        public TicketPriority $priority,
        public string $priorityLabel,
        public TicketSource $source,
        public string $deskId,
        public ?string $slaPolicyId,
        public DeskData|Lazy $desk,
        public ?CarbonImmutable $dueAt,
        #[LiteralTypeScriptType('Record<string, string | number | boolean | string[] | null>')]
        public stdClass $customFields,
        public ContactData|Lazy $requester,
        public Collection|Lazy $assignees,
        public Collection|Lazy $tags,
        public Collection|Lazy $slaTargets,
        public SatisfactionRatingData|Lazy|null $satisfactionRating,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
        public ?CarbonImmutable $deletedAt,
    ) {}

    public static function fromModel(Ticket $ticket): self
    {
        return new self(
            id: $ticket->id,
            number: $ticket->number,
            subject: $ticket->subject,
            status: $ticket->status,
            statusLabel: $ticket->status->label(),
            priority: $ticket->priority,
            priorityLabel: $ticket->priority->label(),
            source: $ticket->source,
            deskId: $ticket->desk_id,
            slaPolicyId: $ticket->sla_policy_id,
            desk: Lazy::whenLoaded('desk', $ticket, fn (): DeskData => DeskData::from($ticket->desk)),
            dueAt: $ticket->due_at,
            customFields: (object) $ticket->custom_fields,
            requester: Lazy::whenLoaded('requester', $ticket, fn (): ContactData => ContactData::from($ticket->requester)),
            assignees: Lazy::whenLoaded('assignees', $ticket, fn (): Collection => UserData::collect($ticket->assignees)),
            tags: Lazy::whenLoaded('tags', $ticket, fn (): Collection => TagData::collect($ticket->tags)),
            slaTargets: Lazy::whenLoaded('slaTargets', $ticket, fn (): Collection => TicketSlaTargetData::collect($ticket->slaTargets)),
            satisfactionRating: Lazy::whenLoaded('satisfactionRating', $ticket, fn (): ?SatisfactionRatingData => $ticket->satisfactionRating === null ? null : SatisfactionRatingData::from($ticket->satisfactionRating)),
            createdAt: $ticket->created_at,
            updatedAt: $ticket->updated_at,
            deletedAt: $ticket->deleted_at,
        );
    }
}
