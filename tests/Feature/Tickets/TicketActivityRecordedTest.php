<?php

use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketEventType;
use App\Events\TicketActivityRecorded;
use App\Models\Desk;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('recording a ticket event dispatches it once the transaction commits', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create();
    $dispatched = [];
    Event::listen(TicketActivityRecorded::class, function (TicketActivityRecorded $event) use (&$dispatched): void {
        $dispatched[] = $event->ticketEvent;
    });

    DB::transaction(function () use ($agent, $ticket, &$dispatched): void {
        app(UpdateTicket::class)->handle($agent, $ticket, ['status' => 'pending']);

        expect($dispatched)->toBeEmpty();
    });

    expect($dispatched)->toHaveCount(1)
        ->and($dispatched[0])
        ->type->toBe(TicketEventType::STATUS_CHANGED)
        ->ticket_id->toBe($ticket->id)
        ->actor_id->toBe($agent->id);
});

test('a rolled-back ticket change dispatches nothing', function () {
    $desk = Desk::factory()->create();
    $agent = deskMember($this->workspace, $desk);
    $ticket = Ticket::factory()->for($desk)->create();
    $dispatched = false;
    Event::listen(TicketActivityRecorded::class, function () use (&$dispatched): void {
        $dispatched = true;
    });

    try {
        DB::transaction(function () use ($agent, $ticket): void {
            app(UpdateTicket::class)->handle($agent, $ticket, ['status' => 'pending']);

            throw new RuntimeException('Roll back');
        });
    } catch (RuntimeException) {
    }

    expect($dispatched)->toBeFalse();
});
