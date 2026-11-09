<?php

use App\Enums\CustomFieldType;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\SatisfactionRating;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketReminder;
use App\Models\User;
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

test('the ticket page shows the thread, sidebar and abilities', function () {
    $ticket = Ticket::factory()->for($this->desk)->create(['subject' => 'Printer jam']);
    $ticket->recordEvent(TicketEventType::CREATED, $this->agent);
    $ticket->recordEvent(TicketEventType::COMMENT_ADDED, $this->agent);
    TicketComment::factory()->for($ticket)->create(['body' => 'Have you tried turning it off?', 'author_id' => $this->agent->id]);
    TicketReminder::factory()->for($ticket)->create(['user_id' => $this->agent->id]);
    TicketReminder::factory()->for($ticket)->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->agent)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Show')
            ->where('ticket.subject', 'Printer jam')
            ->where('ticket.desk.id', $this->desk->id)
            ->has('ticket.desk.custom_fields')
            ->where('comments.0.body', 'Have you tried turning it off?')
            ->where('comments.0.author_name', $this->agent->name)
            ->has('events', 1)
            ->where('events.0.type', 'created')
            ->where('events.0.actor_name', $this->agent->name)
            ->has('reminders', 1)
            ->where('deskMembers.0.id', $this->agent->id)
            ->where('can', ['update' => true, 'delete' => false, 'restore' => false]));
});

test('the ticket page includes the satisfaction rating', function () {
    $ticket = Ticket::factory()->for($this->desk)->create();
    SatisfactionRating::factory()->create(['ticket_id' => $ticket->id, 'rating' => 'bad', 'comment' => 'Slow']);

    $this->actingAs($this->agent)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->where('ticket.satisfaction_rating.rating', 'bad')->where('ticket.satisfaction_rating.comment', 'Slow'));
});

test('core fields are updated and recorded in the history', function () {
    $ticket = Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::OPEN]);

    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $ticket), ['status' => 'pending', 'subject' => 'Renamed'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($ticket->fresh())->status->toBe(TicketStatus::PENDING)->subject->toBe('Renamed')
        ->and($ticket->events()->pluck('type')->all())->toContain(TicketEventType::STATUS_CHANGED, TicketEventType::SUBJECT_CHANGED);

    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $ticket), ['status' => 'nope'])
        ->assertSessionHasErrors('status');
});

test('agents not on the desk are forbidden', function () {
    $ticket = Ticket::factory()->for(Desk::factory()->create())->create();

    $this->actingAs($this->agent)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->actingAs($this->agent)->patch(route('tickets.update', $ticket), ['subject' => 'x'])->assertForbidden();
});

test("another workspace's ticket is not found", function () {
    $foreign = Workspace::factory()->create()->execute(fn () => Ticket::factory()->create());
    $this->workspace->makeCurrent();

    $this->actingAs($this->agent)->get(route('tickets.show', $foreign->id))->assertNotFound();
    $this->actingAs($this->agent)->patch(route('tickets.update', $foreign->id), ['subject' => 'Hijacked'])->assertNotFound();
});

test('only desk leads delete and restore tickets', function () {
    $ticket = Ticket::factory()->for($this->desk)->create();
    $lead = User::factory()->create();
    $this->workspace->users()->attach($lead, ['role' => 'agent']);
    $this->desk->users()->attach($lead, ['role' => DeskRole::LEAD, 'workspace_id' => $this->workspace->id]);

    $this->actingAs($this->agent)->delete(route('tickets.destroy', $ticket))->assertForbidden();

    $this->actingAs($lead)->delete(route('tickets.destroy', $ticket))->assertRedirect(route('tickets.index'));
    expect($ticket->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->agent)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->whereNot('ticket.deleted_at', null));
    $this->actingAs($this->agent)->post(route('tickets.restore', $ticket))->assertForbidden();

    $this->actingAs($lead)->post(route('tickets.restore', $ticket))->assertRedirect();
    expect($ticket->fresh()->trashed())->toBeFalse();
});

test("the list offers only this workspace's choice custom fields", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    Workspace::factory()->create()->execute(fn () => CustomField::factory()->select()->create(['name' => 'Foreign']));
    $this->workspace->makeCurrent();
    $size = CustomField::factory()->select()->create(['name' => 'Size', 'position' => 1]);
    CustomField::factory()->create(['name' => 'Notes', 'type' => CustomFieldType::TEXT]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['preview' => 'tkt_x']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('customFields', 1)
            ->where('customFields.0.id', $size->id)
            ->missing('filters.preview'));
});
