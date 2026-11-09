<?php

use App\Actions\Tickets\SearchTickets;
use App\Enums\CustomFieldType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
});

/**
 * Run the search as the agent and return the matching ticket IDs in order.
 *
 * @param  array<string, mixed>  $filters
 * @return list<string>
 */
function searchIds(User $actor, array $filters = []): array
{
    return app(SearchTickets::class)->handle($actor, $filters)->pluck('id')->all();
}

/**
 * A ticket on the agent's desk with a predictable subject and requester.
 */
function deskTicket(Desk $desk, array $attributes = []): Ticket
{
    return Ticket::factory()->for($desk)->create([
        'subject' => 'Something else entirely',
        'requester_id' => Contact::factory()->create(['name' => 'Sam Smith']),
        ...$attributes,
    ]);
}

test('search matches the subject, comments, number and requester', function () {
    $printer = deskTicket($this->desk, ['subject' => 'The printer is jammed again']);
    $commented = deskTicket($this->desk);
    TicketComment::factory()->for($commented)->create(['body' => 'Restarting the printers fixed it']);
    $byRequester = deskTicket($this->desk, ['requester_id' => Contact::factory()->create(['name' => 'Pat Lee', 'email' => 'pat@acme.test'])]);
    deskTicket($this->desk);

    expect(searchIds($this->agent, ['search' => 'printer']))->toEqualCanonicalizing([$printer->id, $commented->id])
        ->and(searchIds($this->agent, ['search' => "#{$printer->number}"]))->toBe([$printer->id])
        ->and(searchIds($this->agent, ['search' => (string) $printer->number]))->toBe([$printer->id])
        ->and(searchIds($this->agent, ['search' => 'pat lee']))->toBe([$byRequester->id])
        ->and(searchIds($this->agent, ['search' => 'ACME.TEST']))->toBe([$byRequester->id])
        ->and(searchIds($this->agent, ['search' => '%']))->toBe([])
        ->and(searchIds($this->agent, ['search' => '   ']))->toHaveCount(4);
});

test('list filters take one value or several', function () {
    $open = deskTicket($this->desk, ['status' => TicketStatus::OPEN, 'priority' => TicketPriority::LOW]);
    $pending = deskTicket($this->desk, ['status' => TicketStatus::PENDING, 'priority' => TicketPriority::URGENT]);
    deskTicket($this->desk, ['status' => TicketStatus::CLOSED, 'priority' => TicketPriority::NORMAL]);
    $open->tags()->attach(Tag::factory()->create(['name' => 'Billing']), ['workspace_id' => $this->workspace->id]);
    $pending->tags()->attach(Tag::factory()->create(['name' => 'Bug']), ['workspace_id' => $this->workspace->id]);

    expect(searchIds($this->agent, ['status' => 'open']))->toBe([$open->id])
        ->and(searchIds($this->agent, ['status' => ['open', 'pending']]))->toEqualCanonicalizing([$open->id, $pending->id])
        ->and(searchIds($this->agent, ['priority' => ['urgent']]))->toBe([$pending->id])
        ->and(searchIds($this->agent, ['tag' => ['billing', 'BUG']]))->toEqualCanonicalizing([$open->id, $pending->id]);
});

test('desk filter takes several desks the agent can see', function () {
    $other = Desk::factory()->create();
    $other->users()->attach($this->agent, ['role' => 'agent', 'workspace_id' => $this->workspace->id]);
    $mine = deskTicket($this->desk);
    $onOther = deskTicket($other);
    deskTicket(Desk::factory()->create());

    expect(searchIds($this->agent, ['desk_id' => $this->desk->id]))->toBe([$mine->id])
        ->and(searchIds($this->agent, ['desk_id' => [$this->desk->id, $other->id]]))->toEqualCanonicalizing([$mine->id, $onOther->id]);
});

test('assignee filter understands me and none', function () {
    $mine = deskTicket($this->desk);
    $mine->assignees()->attach($this->agent, ['workspace_id' => $this->workspace->id]);
    $unassigned = deskTicket($this->desk);

    expect(searchIds($this->agent, ['assignee_id' => 'me']))->toBe([$mine->id])
        ->and(searchIds($this->agent, ['assignee_id' => $this->agent->id]))->toBe([$mine->id])
        ->and(searchIds($this->agent, ['assignee_id' => 'none']))->toBe([$unassigned->id]);
});

test('requester and deleted filters', function () {
    $contact = Contact::factory()->create();
    $theirs = deskTicket($this->desk, ['requester_id' => $contact->id]);
    $deleted = deskTicket($this->desk);
    $deleted->delete();

    expect(searchIds($this->agent, ['requester_id' => $contact->id]))->toBe([$theirs->id])
        ->and(searchIds($this->agent))->not->toContain($deleted->id)
        ->and(searchIds($this->agent, ['deleted' => '1']))->toBe([$deleted->id]);
});

test('sorts by updated, created, due date, priority, status, number or subject, either way', function () {
    $old = deskTicket($this->desk, ['subject' => 'charlie', 'number' => 1, 'status' => TicketStatus::SOLVED, 'priority' => TicketPriority::URGENT, 'due_at' => null, 'created_at' => now()->subDays(3), 'updated_at' => now()]);
    $middle = deskTicket($this->desk, ['subject' => 'Alpha', 'number' => 2, 'status' => TicketStatus::OPEN, 'priority' => TicketPriority::LOW, 'due_at' => now()->addDay(), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
    $new = deskTicket($this->desk, ['subject' => 'bravo', 'number' => 3, 'status' => TicketStatus::PENDING, 'priority' => TicketPriority::HIGH, 'due_at' => now()->addDays(2), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

    expect(searchIds($this->agent))->toBe([$old->id, $new->id, $middle->id])
        ->and(searchIds($this->agent, ['sort' => 'created']))->toBe([$new->id, $middle->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'due']))->toBe([$middle->id, $new->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'priority']))->toBe([$old->id, $new->id, $middle->id])
        ->and(searchIds($this->agent, ['sort' => 'status']))->toBe([$middle->id, $new->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'number']))->toBe([$new->id, $middle->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'subject']))->toBe([$middle->id, $new->id, $old->id])
        ->and(searchIds($this->agent, ['direction' => 'asc']))->toBe([$middle->id, $new->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'due', 'direction' => 'desc']))->toBe([$new->id, $middle->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'priority', 'direction' => 'asc']))->toBe([$middle->id, $new->id, $old->id]);
});

test('unknown filter values are rejected', function (array $filters, string $field) {
    expect(fn () => searchIds($this->agent, $filters))->toThrow(ValidationException::class, $field);
})->with([
    'status' => [['status' => ['open', 'nope']], 'status'],
    'sort' => [['sort' => 'random'], 'sort'],
    'direction' => [['direction' => 'sideways'], 'direction'],
]);

test("another workspace's tickets never match", function () {
    $foreign = Workspace::factory()->create()->execute(fn () => Ticket::factory()->create(['subject' => 'Printer on fire']));
    $this->workspace->makeCurrent();
    $mine = deskTicket($this->desk, ['subject' => 'Printer offline']);

    expect(searchIds($this->agent, ['search' => 'printer']))->toBe([$mine->id])
        ->and(searchIds($this->agent, ['desk_id' => $foreign->desk_id]))->toBe([]);
});

test('assignee filter matches any of several assignees, none and me included', function () {
    $other = User::factory()->create();
    $mine = deskTicket($this->desk);
    $mine->assignees()->attach($this->agent, ['workspace_id' => $this->workspace->id]);
    $theirs = deskTicket($this->desk);
    $theirs->assignees()->attach($other, ['workspace_id' => $this->workspace->id]);
    $unassigned = deskTicket($this->desk);

    expect(searchIds($this->agent, ['assignee_id' => ['me', $other->id]]))->toEqualCanonicalizing([$mine->id, $theirs->id])
        ->and(searchIds($this->agent, ['assignee_id' => ['none', 'me']]))->toEqualCanonicalizing([$unassigned->id, $mine->id])
        ->and(searchIds($this->agent, ['assignee_id' => ['none']]))->toBe([$unassigned->id]);
});

test('custom field filters match select, multi-select and checkbox values', function () {
    $size = CustomField::factory()->select()->create();
    $areas = CustomField::factory()->create(['type' => CustomFieldType::MULTI_SELECT, 'options' => ['Web', 'API', 'Mobile']]);
    $vip = CustomField::factory()->create(['type' => CustomFieldType::CHECKBOX]);

    $small = deskTicket($this->desk, ['custom_fields' => [$size->id => 'Small', $areas->id => ['Web', 'API'], $vip->id => true]]);
    $large = deskTicket($this->desk, ['custom_fields' => [$size->id => 'Large', $areas->id => ['Mobile'], $vip->id => false]]);
    deskTicket($this->desk);

    expect(searchIds($this->agent, ['custom_fields' => [$size->id => 'Small']]))->toBe([$small->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$size->id => ['Small', 'Large']]]))->toEqualCanonicalizing([$small->id, $large->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$areas->id => ['API', 'Mobile']]]))->toEqualCanonicalizing([$small->id, $large->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$areas->id => 'Mobile']]))->toBe([$large->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$vip->id => '1']]))->toBe([$small->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$vip->id => false]]))->toBe([$large->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$vip->id => ['0', '1']]]))->toEqualCanonicalizing([$small->id, $large->id])
        ->and(searchIds($this->agent, ['custom_fields' => [$size->id => 'Small', $areas->id => 'Mobile']]))->toBe([]);
});

test('only choice custom fields can be filtered on', function () {
    $text = CustomField::factory()->create();

    expect(fn () => searchIds($this->agent, ['custom_fields' => [$text->id => 'x', 'cfd_missing' => 'y']]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKeys(["custom_fields.{$text->id}", 'custom_fields.cfd_missing']));
});

test("another workspace's custom field is rejected and leaks nothing", function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    [$foreignField, $foreignTicket] = Workspace::factory()->create()->execute(function () {
        $field = CustomField::factory()->select()->create();

        return [$field, Ticket::factory()->create(['custom_fields' => [$field->id => 'Small']])];
    });
    $this->workspace->makeCurrent();
    deskTicket($this->desk);

    expect(fn () => searchIds($this->agent, ['custom_fields' => [$foreignField->id => 'Small']]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey("custom_fields.{$foreignField->id}"));
});
