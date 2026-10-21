<?php

use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->agent->update(['name' => 'Jane Doe']);
    $this->ticket = Ticket::factory()->create([
        'desk_id' => $this->desk->id,
        'requester_id' => Contact::factory()->create(['name' => 'Sam Rivers', 'email' => 'sam@example.com']),
        'subject' => 'Broken login',
    ]);
    $this->apply = fn (Macro $macro, ?Ticket $ticket = null) => $this->actingAs($this->agent)
        ->postJson(route('tickets.macros.apply', [$ticket ?? $this->ticket, $macro]));
});

test('every action is applied and the rendered body comes back unsent', function () {
    Mail::fake();
    Notification::fake();
    $this->ticket->tags()->attach(Tag::factory()->create(['name' => 'Spam']), ['workspace_id' => $this->workspace->id]);
    $macro = Macro::factory()->create([
        'body' => 'Hi {{ requester.first_name }}, {{ agent.name }} here',
        'body_is_internal' => true,
        'actions' => ['status' => 'solved', 'priority' => 'high', 'add_tags' => ['VIP'], 'remove_tags' => ['spam'], 'assign_to_me' => true],
    ]);

    ($this->apply)($macro)->assertOk()->assertExactJson(['body' => 'Hi Sam, Jane Doe here', 'is_internal' => true]);

    $ticket = $this->ticket->fresh();
    expect($ticket->status)->toBe(TicketStatus::SOLVED)
        ->and($ticket->priority)->toBe(TicketPriority::HIGH)
        ->and($ticket->tags()->pluck('name')->all())->toBe(['VIP'])
        ->and($ticket->assignees()->pluck('users.id')->all())->toBe([$this->agent->id])
        ->and(TicketComment::count())->toBe(0);
    Mail::assertNothingSent();
    Notification::assertNothingSent();
});

test('actions that change nothing record no events', function () {
    $this->ticket->tags()->attach(Tag::factory()->create(['name' => 'VIP']), ['workspace_id' => $this->workspace->id]);
    $this->ticket->assignees()->attach($this->agent, ['workspace_id' => $this->workspace->id]);
    $macro = Macro::factory()->create(['actions' => [
        'status' => $this->ticket->status->value,
        'priority' => $this->ticket->priority->value,
        'add_tags' => ['vip'],
        'remove_tags' => ['nothing'],
        'assign_to_me' => true,
    ]]);
    $before = $this->ticket->events()->count();

    ($this->apply)($macro)->assertOk();

    expect($this->ticket->events()->count())->toBe($before);
});

test('only the changed actions record events', function () {
    $macro = Macro::factory()->create(['actions' => ['status' => 'pending', 'priority' => $this->ticket->priority->value]]);

    ($this->apply)($macro)->assertOk();

    expect($this->ticket->events()->pluck('type')->all())->toBe([TicketEventType::STATUS_CHANGED]);
});

test('placeholders are replaced and unknown ones are left alone', function () {
    $macro = Macro::factory()->create(['body' => '{{ requester.name }} <{{ requester.email }}> #{{ ticket.number }} "{{ ticket.subject }}" {{ desk.name }} {{ agent.first_name }} {{ ticket.secret }} {{requester.name}}']);

    ($this->apply)($macro)->assertOk()->assertJsonPath('body', "Sam Rivers <sam@example.com> #{$this->ticket->number} \"Broken login\" {$this->desk->name} Jane {{ ticket.secret }} {{requester.name}}");
});

test('a macro without a body returns null', function () {
    $macro = Macro::factory()->create(['body' => null, 'actions' => ['priority' => 'urgent']]);

    ($this->apply)($macro)->assertOk()->assertJsonPath('body', null);
});

test('a desk macro cannot be applied to another desk\'s ticket', function () {
    $other = Desk::factory()->create();
    $this->desk->users()->each(fn ($user) => $other->users()->attach($user, ['role' => 'agent', 'workspace_id' => $this->workspace->id]));
    $macro = Macro::factory()->forDesk($this->desk)->create(['actions' => ['status' => 'solved']]);
    $foreign = Ticket::factory()->create(['desk_id' => $other->id]);

    ($this->apply)($macro, $foreign)->assertForbidden();

    expect($foreign->fresh()->status)->not->toBe(TicketStatus::SOLVED);
});

test('another user\'s personal macro is forbidden', function () {
    $macro = Macro::factory()->personal(deskMember($this->workspace, $this->desk))->create(['actions' => ['status' => 'solved']]);

    ($this->apply)($macro)->assertForbidden();

    expect($this->ticket->fresh()->status)->not->toBe(TicketStatus::SOLVED);
});

test('the API applies a macro too', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    actAs($this->agent, $this->workspace);
    $macro = Macro::factory()->create(['body' => 'Hello {{ requester.first_name }}', 'actions' => ['status' => 'solved']]);

    $this->postJson("http://acme.harbor.test/api/v1/tickets/{$this->ticket->id}/macros/{$macro->id}/apply")
        ->assertOk()->assertExactJson(['body' => 'Hello Sam', 'is_internal' => false]);

    expect($this->ticket->fresh()->status)->toBe(TicketStatus::SOLVED);
});

test('the ticket page defers the macros usable on the ticket', function () {
    $usable = Macro::factory()->create();
    Macro::factory()->forDesk(Desk::factory()->create())->create();

    $this->actingAs($this->agent)->get(route('tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page->missing('macros')
            ->loadDeferredProps(fn (Assert $reloaded) => $reloaded->has('macros', 1)->where('macros.0.id', $usable->id)));
});
