<?php

use App\Mcp\Servers\HarborServer;
use App\Mcp\Tools\GetContactTool;
use App\Mcp\Tools\GetTicketStatsTool;
use App\Mcp\Tools\GetTicketTool;
use App\Mcp\Tools\SearchContactsTool;
use App\Mcp\Tools\SearchTicketsTool;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;

beforeEach(function () {
    ['desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'subject' => 'Printer on fire']);
    $this->hidden = Ticket::factory()->create(['desk_id' => Desk::factory()->create(['name' => 'Secret desk']), 'subject' => 'Hidden matter']);
});

test('search_tickets lists only tickets on visible desks and hints at paging', function () {
    HarborServer::actingAs($this->agent)->tool(SearchTicketsTool::class)
        ->assertOk()
        ->assertSee(['Printer on fire', '"total":1'])
        ->assertDontSee('Hidden matter');
});

test('search_tickets pages at 25', function () {
    Ticket::factory()->count(25)->create(['desk_id' => $this->desk->id]);

    HarborServer::actingAs($this->agent)->tool(SearchTicketsTool::class)
        ->assertSee(['"next_page":2', '"total":26']);
    HarborServer::actingAs($this->agent)->tool(SearchTicketsTool::class, ['page' => 2])
        ->assertSee(['"page":2', '"next_page":null']);
});

test('get_ticket resolves a number or an id and flags internal notes', function () {
    TicketComment::factory()->internal()->create(['ticket_id' => $this->ticket->id, 'body' => 'Secret note']);

    foreach ([(string) $this->ticket->number, "#{$this->ticket->number}", $this->ticket->id] as $reference) {
        HarborServer::actingAs($this->agent)->tool(GetTicketTool::class, ['ticket' => $reference])
            ->assertOk()
            ->assertSee(['Printer on fire', 'Secret note', '"is_internal":true']);
    }
});

test('get_ticket cannot see another desk\'s ticket by number or id', function () {
    foreach ([(string) $this->hidden->number, $this->hidden->id] as $reference) {
        HarborServer::actingAs($this->agent)->tool(GetTicketTool::class, ['ticket' => $reference])
            ->assertHasErrors(["Ticket {$reference} not found."])
            ->assertDontSee('Hidden matter');
    }
});

test('get_ticket on an unknown ticket is a clear error', function () {
    HarborServer::actingAs($this->agent)->tool(GetTicketTool::class, ['ticket' => '#999'])
        ->assertHasErrors(['Ticket #999 not found.']);
});

test('a ticket number from workspace B never resolves on workspace A', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    $theirs = Ticket::factory()->create(['desk_id' => Desk::where('workspace_id', $other->id)->first()->id, 'subject' => 'Other workspace']);
    Workspace::where('subdomain', 'acme')->first()->makeCurrent();
    Ticket::factory()->create(['desk_id' => $this->desk->id]);

    foreach ([(string) $theirs->number, $theirs->id] as $reference) {
        $response = HarborServer::actingAs($this->agent)->tool(GetTicketTool::class, ['ticket' => $reference]);
        $reference === $theirs->id
            ? $response->assertHasErrors()->assertDontSee('Other workspace')
            : $response->assertDontSee('Other workspace');
    }

    HarborServer::actingAs($this->agent)->tool(SearchTicketsTool::class)->assertDontSee('Other workspace');
});

test('search_contacts and get_contact work, with recent tickets limited to visible desks', function () {
    $contact = Contact::factory()->create(['name' => 'Casey Customer']);
    Ticket::factory()->create(['desk_id' => $this->desk->id, 'requester_id' => $contact->id, 'subject' => 'Visible one']);
    Ticket::factory()->create(['desk_id' => $this->hidden->desk_id, 'requester_id' => $contact->id, 'subject' => 'Hidden one']);

    HarborServer::actingAs($this->agent)->tool(SearchContactsTool::class, ['search' => 'Casey'])
        ->assertOk()->assertSee(['Casey Customer', '"total":1']);
    HarborServer::actingAs($this->agent)->tool(GetContactTool::class, ['contact' => $contact->id])
        ->assertOk()->assertSee(['Casey Customer', 'Visible one'])->assertDontSee('Hidden one');
    HarborServer::actingAs($this->agent)->tool(GetContactTool::class, ['contact' => 'ctc_missing'])
        ->assertHasErrors(['Contact ctc_missing not found.']);
});

test('get_ticket_stats counts only visible desks', function () {
    HarborServer::actingAs($this->agent)->tool(GetTicketStatsTool::class, ['report' => 'overview'])
        ->assertOk()
        ->assertSee($this->desk->name)
        ->assertDontSee('Secret desk');
});
