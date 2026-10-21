<?php

use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
});

test('the form lists the agent\'s desks with their fields and members', function () {
    $field = CustomField::factory()->create();
    $this->desk->customFields()->attach($field, ['workspace_id' => $this->workspace->id]);
    Desk::factory()->create();

    $this->actingAs($this->agent)
        ->get(route('tickets.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('tickets/Create')
            ->where('desks', [[
                'id' => $this->desk->id,
                'name' => $this->desk->name,
                'slug' => $this->desk->slug,
                'assignment_strategy' => 'manual',
                'custom_fields' => [[
                    'id' => $field->id,
                    'name' => $field->name,
                    'type' => $field->type->value,
                    'type_label' => $field->type->label(),
                    'options' => $field->options,
                    'is_required' => $field->is_required,
                    'position' => $field->position,
                ]],
                'members' => [['id' => $this->agent->id, 'name' => $this->agent->name, 'email' => $this->agent->email, 'role' => 'agent', 'in_rotation' => true]],
                'archived_at' => null,
            ]])
            ->where('statuses.open', 'Open'));
});

test('agents open a ticket for an existing contact with a first message and files', function () {
    $contact = Contact::factory()->create();

    $this->actingAs($this->agent)
        ->post(route('desks.tickets.store', $this->desk), [
            'subject' => 'Printer jam',
            'requester_id' => $contact->id,
            'priority' => 'high',
            'assignee_ids' => [$this->agent->id],
            'body' => 'It jams on double-sided prints.',
            'attachments' => [UploadedFile::fake()->createWithContent('photo.txt', 'jam')],
        ])
        ->assertSessionHasNoErrors();

    $ticket = Ticket::sole();
    $comment = $ticket->comments()->with('attachments')->sole();

    expect($ticket)->requester_id->toBe($contact->id)->priority->value->toBe('high')
        ->and($ticket->assignees()->pluck('users.id')->all())->toBe([$this->agent->id])
        ->and($comment)->body->toBe('It jams on double-sided prints.')->is_internal->toBeFalse()
        ->and($comment->attachments->sole()->filename)->toBe('photo.txt');
});

test('the requester can be a new contact, and the agent lands on the ticket', function () {
    $response = $this->actingAs($this->agent)->post(route('desks.tickets.store', $this->desk), [
        'subject' => 'VPN down',
        'requester' => ['name' => 'Sam Rivera', 'email' => 'sam@acme.test'],
    ]);

    $ticket = Ticket::sole();
    $response->assertRedirect(route('tickets.show', $ticket));
    expect($ticket->requester)->name->toBe('Sam Rivera')->email->toBe('sam@acme.test')
        ->and($ticket->comments()->count())->toBe(0);
});

test('required custom fields and files without a message are rejected', function () {
    $field = CustomField::factory()->create(['type' => 'text', 'is_required' => true]);
    $this->desk->customFields()->attach($field, ['workspace_id' => $this->workspace->id]);
    $contact = Contact::factory()->create();

    $this->actingAs($this->agent)
        ->post(route('desks.tickets.store', $this->desk), [
            'subject' => 'Missing field',
            'requester_id' => $contact->id,
            'attachments' => [UploadedFile::fake()->create('orphan.txt')],
        ])
        ->assertSessionHasErrors(["custom_fields.{$field->id}", 'body']);

    expect(Ticket::count())->toBe(0);
});

test('agents cannot open tickets on desks they are not on', function () {
    $other = Desk::factory()->create();

    $this->actingAs($this->agent)
        ->post(route('desks.tickets.store', $other), ['subject' => 'x', 'requester' => ['name' => 'X', 'email' => 'x@acme.test']])
        ->assertForbidden();

    $foreignDesk = Workspace::factory()->create()->execute(fn () => Desk::sole());
    $this->workspace->makeCurrent();
    $this->actingAs($this->agent)
        ->post(route('desks.tickets.store', $foreignDesk->id), ['subject' => 'x', 'requester' => ['name' => 'X', 'email' => 'x@acme.test']])
        ->assertNotFound();

    expect(Ticket::count())->toBe(0);
});
