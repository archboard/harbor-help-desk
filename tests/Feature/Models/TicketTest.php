<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\Workspace;

test('tickets are numbered per workspace starting at one', function () {
    Workspace::factory()->create()->execute(fn () => Ticket::factory()->create());
    Workspace::factory()->create()->makeCurrent();

    $tickets = Ticket::factory()->count(2)->create();

    expect($tickets->pluck('number')->all())->toBe([1, 2]);
});

test('new tickets are open with normal priority', function () {
    Workspace::factory()->create()->makeCurrent();

    expect(Ticket::factory()->create()->fresh())
        ->status->toBe(TicketStatus::OPEN)
        ->priority->toBe(TicketPriority::NORMAL);
});

test('workspace b cannot read workspace a tickets', function () {
    $ticketA = Workspace::factory()->create()->execute(fn () => Ticket::factory()->create());
    Workspace::factory()->create()->makeCurrent();

    expect(Ticket::find($ticketA->id))->toBeNull();
});
