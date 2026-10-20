<?php

use App\Actions\Tickets\MoveTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\DeskRole;
use App\Events\TicketChanged;
use App\Models\Desk;
use App\Models\Ticket;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @return list<string>
 */
function channelNames(TicketChanged $event): array
{
    return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn());
}

test('updating a ticket pings its ticket and desk channels with ids only', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create();
    Event::fake([TicketChanged::class]);

    app(UpdateTicket::class)->handle($agent, $ticket, ['status' => 'pending']);

    Event::assertDispatched(TicketChanged::class, function (TicketChanged $event) use ($ticket, $desk, $agent): bool {
        expect(channelNames($event))->toBe(["private-tickets.{$ticket->id}", "private-desks.{$desk->id}"])
            ->and($event->broadcastWith())->toBe([
                'ticket_id' => $ticket->id,
                'desk_id' => $desk->id,
                'type' => 'status_changed',
                'actor_id' => $agent->id,
            ])
            ->and($event->broadcastAs())->toBe('TicketChanged')
            ->and($event->broadcastQueue())->toBe('broadcasts');

        return true;
    });
});

test('moving a ticket pings the new and the old desk', function () {
    $from = Desk::factory()->create();
    $to = Desk::factory()->create();
    $agent = deskMember($this->workspace, $from);
    $to->users()->attach($agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $ticket = Ticket::factory()->for($from)->create();
    Event::fake([TicketChanged::class]);

    app(MoveTicket::class)->handle($agent, $ticket, $to);

    Event::assertDispatched(TicketChanged::class, fn (TicketChanged $event): bool => channelNames($event) === [
        "private-tickets.{$ticket->id}",
        "private-desks.{$to->id}",
        "private-desks.{$from->id}",
    ]);
});

test('the window that made the change is excluded by its socket id', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create();
    Event::fake([TicketChanged::class]);

    $this->actingAs($agent)
        ->withHeader('X-Socket-ID', '123.456')
        ->patch(route('tickets.update', $ticket), ['status' => 'pending']);

    Event::assertDispatched(TicketChanged::class, fn (TicketChanged $event): bool => $event->socket === '123.456');
});
