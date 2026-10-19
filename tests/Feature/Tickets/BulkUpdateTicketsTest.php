<?php

use App\Enums\DeskRole;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->tickets = Ticket::factory()->for($this->desk)->count(2)->create(['status' => TicketStatus::OPEN]);
    $this->ids = $this->tickets->modelKeys();
});

test('status, priority, assignees, tags and desk are applied to every ticket', function () {
    $billing = Desk::factory()->create(['name' => 'Billing']);
    $billing->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $this->tickets[0]->tags()->attach(Tag::factory()->create(['name' => 'vip']), ['workspace_id' => $this->workspace->id]);
    $this->actingAs($this->agent);

    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'status' => 'solved'])->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'priority' => 'urgent'])->assertSessionHasNoErrors();
    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'assignee_ids' => [$this->agent->id]])->assertSessionHasNoErrors();
    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'add_tag' => 'Refund'])->assertSessionHasNoErrors();
    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'desk_id' => $billing->id])->assertSessionHasNoErrors();

    foreach ($this->tickets as $ticket) {
        $ticket->refresh();
        expect($ticket->status)->toBe(TicketStatus::SOLVED)
            ->and($ticket->priority)->toBe(TicketPriority::URGENT)
            ->and($ticket->assignees->modelKeys())->toBe([$this->agent->id])
            ->and($ticket->desk_id)->toBe($billing->id)
            ->and($ticket->tags->pluck('name'))->toContain('Refund');
    }

    expect($this->tickets[0]->tags->pluck('name')->sort()->values()->all())->toBe(['Refund', 'vip']);
});

test('desk leads delete in bulk', function () {
    $lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD, ['email' => 'lead@acme.test']);

    $this->actingAs($this->agent)->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('leadDeskIds', []));
    $this->actingAs($lead)->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('leadDeskIds', [$this->desk->id]));

    $this->actingAs($lead)->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'delete' => true])->assertSessionHasNoErrors();

    expect(Ticket::count())->toBe(0)->and(Ticket::onlyTrashed()->count())->toBe(2);
});

test('one ticket the actor may not change rolls the whole batch back', function () {
    $other = Desk::factory()->create();
    $other->users()->attach($this->agent, ['role' => DeskRole::LEAD, 'workspace_id' => $this->workspace->id]);
    $theirs = Ticket::factory()->for($other)->create();

    // The agent leads the other desk but not this one, so only that ticket could be deleted.
    $this->actingAs($this->agent)
        ->post(route('tickets.bulk'), ['ticket_ids' => [$theirs->id, ...$this->ids], 'delete' => true])
        ->assertForbidden();

    expect(Ticket::count())->toBe(3);
});

test('a failure on one ticket is reported against the operation and nothing changes', function () {
    $other = Desk::factory()->create();
    $other->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $outsider = deskMember($this->workspace, $other, attributes: ['email' => 'outsider@acme.test']);
    Ticket::factory()->for($other)->create();

    // The outsider is only on the other desk, so assigning them to this desk's tickets fails.
    $this->actingAs($this->agent)
        ->post(route('tickets.bulk'), ['ticket_ids' => Ticket::pluck('id')->all(), 'assignee_ids' => [$outsider->id]])
        ->assertSessionHasErrors('assignee_ids');

    expect(Ticket::whereHas('assignees')->count())->toBe(0);
});

test('requests need exactly one operation and tickets the actor can see', function () {
    $hidden = Ticket::factory()->for(Desk::factory()->create())->create();
    $this->actingAs($this->agent);

    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids])->assertSessionHasErrors('operation');
    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'status' => 'solved', 'priority' => 'low'])->assertSessionHasErrors('operation');
    $this->post(route('tickets.bulk'), ['ticket_ids' => [...$this->ids, $hidden->id], 'status' => 'solved'])->assertSessionHasErrors('ticket_ids');
    $this->post(route('tickets.bulk'), ['ticket_ids' => [], 'status' => 'solved'])->assertSessionHasErrors('ticket_ids');
    $this->post(route('tickets.bulk'), ['ticket_ids' => $this->ids, 'desk_id' => 'dsk_missing'])->assertSessionHasErrors('desk_id');

    expect(Ticket::where('status', TicketStatus::SOLVED)->count())->toBe(0);
});

test('the API applies the change and rejects another workspace\'s tickets', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    actAs($this->agent, $this->workspace);
    $foreign = Workspace::factory()->create(['subdomain' => 'other'])->execute(fn () => Ticket::factory()->create());
    $this->workspace->makeCurrent();

    $this->postJson('http://acme.harbor.test/api/v1/tickets/bulk', ['ticket_ids' => $this->ids, 'status' => 'pending'])
        ->assertOk()
        ->assertExactJson(['updated' => 2]);

    $this->postJson('http://acme.harbor.test/api/v1/tickets/bulk', ['ticket_ids' => [$foreign->id], 'status' => 'closed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ticket_ids');
    expect(Workspace::find($foreign->workspace_id)->execute(fn () => $foreign->fresh()->status))->toBe($foreign->status);
});
