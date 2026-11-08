<?php

namespace App\Data\Webhooks;

use App\Data\Tickets\TicketData;
use App\Data\Tickets\TicketEventData;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * The JSON body of a webhook request. A `ping` has neither an event nor a ticket.
 */
class WebhookPayloadData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public CarbonImmutable $occurredAt,
        public string $workspaceId,
        public ?TicketEventData $event,
        public ?TicketData $ticket,
    ) {}
}
