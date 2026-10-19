<?php

use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
});

test('the list searches by name or email and counts visible tickets', function () {
    $pat = Contact::factory()->create(['name' => 'Pat Lee', 'email' => 'pat@acme.test']);
    Contact::factory()->create(['name' => 'Sam Rivera', 'email' => 'sam@other.test']);
    Ticket::factory()->for($this->desk)->create(['requester_id' => $pat->id]);
    Ticket::factory()->for(Desk::factory()->create())->create(['requester_id' => $pat->id]);

    $this->actingAs($this->agent)
        ->get(route('contacts.index', ['search' => 'ACME']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $pat->id)
            ->where('contacts.data.0.tickets_count', 1)
            ->where('filters', ['search' => 'ACME']));
});

test('the search endpoint returns matching contacts for pickers', function () {
    Contact::factory()->create(['name' => 'Pat Lee']);
    Contact::factory()->create(['name' => 'Sam Rivera']);

    $this->actingAs($this->agent)
        ->getJson(route('contacts.search', ['search' => 'pat']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Pat Lee');
});

test('contacts are created and updated, and emails stay unique ignoring case', function () {
    Contact::factory()->create(['email' => 'taken@acme.test']);
    $this->actingAs($this->agent);

    $this->post(route('contacts.store'), ['name' => 'Dup', 'email' => 'TAKEN@acme.test'])->assertSessionHasErrors('email');

    $this->post(route('contacts.store'), ['name' => 'Pat Lee', 'email' => 'pat@acme.test'])->assertRedirect();
    $pat = Contact::where('email', 'pat@acme.test')->sole();

    $this->patch(route('contacts.update', $pat), ['name' => 'Patricia Lee', 'email' => 'PAT@acme.test'])->assertSessionHasNoErrors();
    expect($pat->fresh()->name)->toBe('Patricia Lee');

    $this->patch(route('contacts.update', $pat), ['email' => 'taken@ACME.test'])->assertSessionHasErrors('email');
});

test('the contact page only lists tickets on the agent\'s desks', function () {
    $contact = Contact::factory()->create();
    $visible = Ticket::factory()->for($this->desk)->create(['requester_id' => $contact->id]);
    Ticket::factory()->for(Desk::factory()->create())->create(['requester_id' => $contact->id]);

    $this->actingAs($this->agent)
        ->get(route('contacts.show', $contact))
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/Show')
            ->where('contact.id', $contact->id)
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $visible->id));
});

test('non-members cannot reach contacts', function () {
    $contact = Contact::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(route('contacts.index'))->assertForbidden();
    $this->get(route('contacts.show', $contact))->assertForbidden();
    $this->post(route('contacts.store'), ['name' => 'X', 'email' => 'x@acme.test'])->assertForbidden();
});

test("another workspace's contacts are not found", function () {
    $foreign = Workspace::factory()->create()->execute(fn () => Contact::factory()->create(['name' => 'Foreign']));
    $this->workspace->makeCurrent();
    $this->actingAs($this->agent);

    $this->get(route('contacts.show', $foreign->id))->assertNotFound();
    $this->patch(route('contacts.update', $foreign->id), ['name' => 'Hijacked'])->assertNotFound();
    $this->get(route('contacts.index'))->assertInertia(fn (Assert $page) => $page->has('contacts.data', 0));
    expect($foreign->fresh()->name)->toBe('Foreign');
});
