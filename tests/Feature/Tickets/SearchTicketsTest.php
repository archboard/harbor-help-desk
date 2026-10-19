<?php

use App\Actions\Tickets\SearchTickets;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Contact;
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

test('sorts by updated, created, due date or priority', function () {
    $old = deskTicket($this->desk, ['priority' => TicketPriority::URGENT, 'due_at' => null, 'created_at' => now()->subDays(3), 'updated_at' => now()]);
    $middle = deskTicket($this->desk, ['priority' => TicketPriority::LOW, 'due_at' => now()->addDay(), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)]);
    $new = deskTicket($this->desk, ['priority' => TicketPriority::HIGH, 'due_at' => now()->addDays(2), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

    expect(searchIds($this->agent))->toBe([$old->id, $new->id, $middle->id])
        ->and(searchIds($this->agent, ['sort' => 'created']))->toBe([$new->id, $middle->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'due']))->toBe([$middle->id, $new->id, $old->id])
        ->and(searchIds($this->agent, ['sort' => 'priority']))->toBe([$old->id, $new->id, $middle->id]);
});

test('unknown filter values are rejected', function (array $filters, string $field) {
    expect(fn () => searchIds($this->agent, $filters))->toThrow(ValidationException::class, $field);
})->with([
    'status' => [['status' => ['open', 'nope']], 'status'],
    'sort' => [['sort' => 'random'], 'sort'],
]);

test("another workspace's tickets never match", function () {
    $foreign = Workspace::factory()->create()->execute(fn () => Ticket::factory()->create(['subject' => 'Printer on fire']));
    $this->workspace->makeCurrent();
    $mine = deskTicket($this->desk, ['subject' => 'Printer offline']);

    expect(searchIds($this->agent, ['search' => 'printer']))->toBe([$mine->id])
        ->and(searchIds($this->agent, ['desk_id' => $foreign->desk_id]))->toBe([]);
});
