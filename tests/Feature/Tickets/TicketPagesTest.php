<?php

use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
});

test('the list shows the tickets and the filter options', function () {
    $ticket = Ticket::factory()->for($this->desk)->create(['subject' => 'Printer jam']);
    $ticket->assignees()->attach($this->agent, ['workspace_id' => $this->workspace->id]);
    Tag::factory()->create(['name' => 'Billing']);

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.subject', 'Printer jam')
            ->where('tickets.data.0.status_label', 'Open')
            ->where('tickets.data.0.desk.id', $this->desk->id)
            ->where('tickets.data.0.requester.id', $ticket->requester_id)
            ->where('tickets.data.0.assignees.0.id', $this->agent->id)
            ->where('desks.0.id', $this->desk->id)
            ->where('agents.0.id', $this->agent->id)
            ->where('tags.0.name', 'Billing')
            ->where('statuses.on_hold', 'On hold')
            ->where('priorities.urgent', 'Urgent'));
});

test('agents only see tickets on their desks', function () {
    $mine = Ticket::factory()->for($this->desk)->create();
    Ticket::factory()->for(Desk::factory()->create())->create();

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $mine->id)
            ->has('desks', 1));
});

test('filters from the query string are applied and passed back', function () {
    $pending = Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::PENDING]);
    Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::OPEN]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['status' => 'pending', 'page' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $pending->id)
            ->where('filters', ['status' => 'pending']));
});

test("another workspace's tickets are absent", function () {
    Workspace::factory()->create()->execute(fn () => Ticket::factory()->create());
    $this->workspace->makeCurrent();

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 0));
});
