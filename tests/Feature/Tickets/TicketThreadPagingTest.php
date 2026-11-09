<?php

use App\Enums\TicketEventType;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->ticket = Ticket::factory()->for($this->desk)->create();
    $start = now()->subDays(10)->startOfMinute();

    // Comment n at minute 2n, with an event a minute after it.
    foreach (range(0, 119) as $n) {
        TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'body' => "c$n", 'created_at' => $start->copy()->addMinutes(2 * $n)]);
        TicketEvent::factory()->create(['ticket_id' => $this->ticket->id, 'type' => TicketEventType::STATUS_CHANGED, 'properties' => ['from' => 'open', 'to' => 'pending'], 'created_at' => $start->copy()->addMinutes(2 * $n + 1)]);
    }
    // Comment events are shown as comments, so never as events.
    TicketEvent::factory()->create(['ticket_id' => $this->ticket->id, 'type' => TicketEventType::COMMENT_ADDED, 'created_at' => $start]);
});

test('the ticket page loads the newest 50 comments, oldest first, with the cursor for earlier ones', function () {
    $this->actingAs($this->agent)
        ->get(route('tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 50)
            ->where('comments.0.body', 'c70')
            ->where('comments.49.body', 'c119')
            ->where('earlier_before', TicketComment::where('body', 'c70')->value('id'))
            // From c70 on: c70's own event is after it, so 50 events.
            ->has('events', 50));
});

test('the preview prop is paged too', function () {
    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['preview' => $this->ticket->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('preview.comments', 50)
            ->where('preview.earlier_before', TicketComment::where('body', 'c70')->value('id')));
});

test('earlier pages chain back without dropping or repeating a comment or event', function () {
    $this->actingAs($this->agent);
    $comments = collect($this->get(route('tickets.show', $this->ticket))->inertiaProps('comments'))->pluck('id');
    $events = collect($this->get(route('tickets.show', $this->ticket))->inertiaProps('events'))->pluck('id');
    $before = $this->get(route('tickets.show', $this->ticket))->inertiaProps('earlier_before');
    $pages = 0;

    while ($before !== null) {
        $page = $this->getJson(route('tickets.thread', [$this->ticket, 'before' => $before]))->assertOk()->json();
        $comments = collect($page['comments'])->pluck('id')->merge($comments);
        $events = collect($page['events'])->pluck('id')->merge($events);
        $before = $page['earlier_before'];
        $pages++;
    }

    expect($pages)->toBe(2)
        ->and($comments)->toHaveCount(120)->and($comments->unique())->toHaveCount(120)
        ->and($events)->toHaveCount(120)->and($events->unique())->toHaveCount(120)
        ->and($comments->all())->toBe(TicketComment::orderBy('created_at')->pluck('id')->all());
});

test('before returns only the comments before it', function () {
    $cursor = TicketComment::where('body', 'c70')->value('id');

    $this->actingAs($this->agent)
        ->getJson(route('tickets.thread', [$this->ticket, 'before' => $cursor]))
        ->assertOk()
        ->assertJsonCount(50, 'comments')
        ->assertJsonPath('comments.0.body', 'c20')
        ->assertJsonPath('comments.49.body', 'c69')
        ->assertJsonPath('earlier_before', TicketComment::where('body', 'c20')->value('id'));
});

test("another ticket's comment as the cursor returns an empty page", function () {
    $foreign = TicketComment::factory()->create();

    $this->actingAs($this->agent)
        ->getJson(route('tickets.thread', [$this->ticket, 'before' => $foreign->id]))
        ->assertOk()
        ->assertJsonCount(0, 'comments')
        ->assertJsonCount(0, 'events')
        ->assertJsonPath('earlier_before', null);
});

test('agents cannot page the thread of a ticket they cannot view', function () {
    $this->actingAs($this->agent)
        ->getJson(route('tickets.thread', Ticket::factory()->create()))
        ->assertForbidden();
});

test("another workspace's comment as the cursor, and its ticket, are out of reach", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    [$foreignTicket, $foreignComment] = $other->execute(function () {
        $ticket = Ticket::factory()->create();

        return [$ticket, TicketComment::factory()->create(['ticket_id' => $ticket->id])];
    });
    $this->workspace->makeCurrent();
    $this->actingAs($this->agent);

    $this->getJson("http://acme.harbor.test/tickets/{$foreignTicket->id}/thread")->assertNotFound();
    $this->getJson("http://acme.harbor.test/tickets/{$this->ticket->id}/thread?before={$foreignComment->id}")
        ->assertOk()->assertJsonCount(0, 'comments')->assertJsonCount(0, 'events');
});

test('the portal thread shows the newest 25 public comments and pages back', function () {
    $contact = Contact::factory()->create();
    $ticket = Ticket::factory()->create(['requester_id' => $contact->id]);
    foreach (range(1, 30) as $n) {
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'body' => "p$n", 'created_at' => now()->subMinutes(100 - $n)]);
    }
    TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'created_at' => now()->subMinutes(200)]);
    $this->actingAs($contact, 'contact');

    $this->get(route('portal.tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 25)
            ->where('comments.0.body', 'p6')
            ->where('earlier_before', TicketComment::where('body', 'p6')->value('id')));

    $this->getJson(route('portal.tickets.thread', [$ticket, 'before' => TicketComment::where('body', 'p6')->value('id')]))
        ->assertOk()
        ->assertJsonCount(5, 'comments')
        ->assertJsonPath('earlier_before', null);

    $other = Ticket::factory()->create();
    $this->getJson(route('portal.tickets.thread', $other))->assertNotFound();
});
