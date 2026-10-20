<?php

namespace App\Events;

use App\Enums\TicketEventType;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A ping that a ticket changed. It carries ids and the event type only, so clients reload the
 * ticket through their own policies. A move also pings the desk the ticket left.
 */
class TicketChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $ticket_id,
        public string $desk_id,
        public TicketEventType $type,
        public ?string $actor_id,
        public ?string $from_desk_id = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (string $name): PrivateChannel => new PrivateChannel($name),
            array_values(array_filter([
                "tickets.{$this->ticket_id}",
                "desks.{$this->desk_id}",
                $this->from_desk_id === null ? null : "desks.{$this->from_desk_id}",
            ])),
        );
    }

    public function broadcastAs(): string
    {
        return 'TicketChanged';
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    /**
     * @return array{ticket_id: string, desk_id: string, type: string, actor_id: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket_id,
            'desk_id' => $this->desk_id,
            'type' => $this->type->value,
            'actor_id' => $this->actor_id,
        ];
    }
}
