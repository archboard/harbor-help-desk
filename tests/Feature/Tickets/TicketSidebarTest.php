<?php

use App\Enums\DeskRole;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketReminder;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->ticket = Ticket::factory()->for($this->desk)->create();
});

test('tickets move to desks the agent is on, but not others', function () {
    $other = Desk::factory()->create();
    $other->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $this->workspace->id]);
    $notMine = Desk::factory()->create();
    $this->actingAs($this->agent);

    $this->put(route('tickets.desk.update', $this->ticket), ['desk_id' => $notMine->id])->assertForbidden();
    expect($this->ticket->fresh()->desk_id)->toBe($this->desk->id);

    $this->put(route('tickets.desk.update', $this->ticket), ['desk_id' => $other->id])->assertRedirect();
    expect($this->ticket->fresh()->desk_id)->toBe($other->id);
});

test('assignees are desk members only', function () {
    $this->actingAs($this->agent);

    $this->put(route('tickets.assignees.update', $this->ticket), ['user_ids' => [$this->agent->id]])->assertSessionHasNoErrors();
    expect($this->ticket->assignees()->pluck('users.id')->all())->toBe([$this->agent->id]);

    $this->put(route('tickets.assignees.update', $this->ticket), ['user_ids' => [User::factory()->create()->id]])
        ->assertSessionHasErrors('user_ids');

    $this->put(route('tickets.assignees.update', $this->ticket), ['user_ids' => []])->assertSessionHasNoErrors();
    expect($this->ticket->assignees()->count())->toBe(0);
});

test('tags are added and removed by name', function () {
    $this->actingAs($this->agent);

    $this->put(route('tickets.tags.update', $this->ticket), ['names' => ['Billing', 'Urgent']])->assertSessionHasNoErrors();
    expect($this->ticket->tags()->orderBy('name')->pluck('name')->all())->toBe(['Billing', 'Urgent']);

    $this->put(route('tickets.tags.update', $this->ticket), ['names' => ['Billing']]);
    expect($this->ticket->tags()->pluck('name')->all())->toBe(['Billing']);

    $this->get(route('tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page->where('tags.0.name', 'Billing'));
});

test('custom field values are saved through the ticket update', function () {
    $field = CustomField::factory()->create(['name' => 'Order', 'type' => 'number']);
    $this->desk->customFields()->attach($field, ['workspace_id' => $this->workspace->id]);
    $this->actingAs($this->agent);

    $this->patch(route('tickets.update', $this->ticket), ['custom_fields' => [$field->id => '42']])->assertSessionHasNoErrors();
    expect($this->ticket->fresh()->custom_fields)->toBe([$field->id => 42]);

    $this->patch(route('tickets.update', $this->ticket), ['custom_fields' => [$field->id => 'lots']])
        ->assertSessionHasErrors("custom_fields.{$field->id}");
});

test('reminders are set and cancelled by whoever may', function () {
    $this->actingAs($this->agent);

    $this->post(route('tickets.reminders.store', $this->ticket), ['remind_at' => now()->addDay()->toIso8601String(), 'note' => 'Chase'])
        ->assertSessionHasNoErrors();
    $mine = TicketReminder::sole();
    expect($mine)->user_id->toBe($this->agent->id)->note->toBe('Chase');

    $this->post(route('tickets.reminders.store', $this->ticket), ['remind_at' => now()->subDay()->toIso8601String()])
        ->assertSessionHasErrors('remind_at');

    $someoneElses = TicketReminder::factory()->for($this->ticket)->create(['user_id' => null]);
    $this->delete(route('reminders.destroy', $someoneElses))->assertForbidden();

    $this->delete(route('reminders.destroy', $mine))->assertRedirect();
    expect(TicketReminder::pluck('id')->all())->toBe([$someoneElses->id]);
});

test("another workspace's tickets and reminders are not found", function () {
    [$ticket, $reminder] = Workspace::factory()->create()->execute(function () {
        $ticket = Ticket::factory()->create();

        return [$ticket, TicketReminder::factory()->for($ticket)->create()];
    });
    $this->workspace->makeCurrent();
    $this->actingAs($this->agent);

    $this->put(route('tickets.tags.update', $ticket->id), ['names' => ['x']])->assertNotFound();
    $this->put(route('tickets.desk.update', $this->ticket), ['desk_id' => $ticket->desk_id])->assertNotFound();
    $this->delete(route('reminders.destroy', $reminder->id))->assertNotFound();
});
