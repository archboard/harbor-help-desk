<?php

use App\Enums\TicketEventType;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('events describe what happened in a sentence', function (TicketEventType $type, array $properties, string $expected) {
    $ticket = Ticket::factory()->create();
    $actor = User::factory()->create(['name' => 'Jane Doe']);

    expect($ticket->recordEvent($type, $actor, $properties)->description())->toBe($expected);
})->with([
    'created' => [TicketEventType::CREATED, [], 'Jane Doe created a ticket.'],
    'subject' => [TicketEventType::SUBJECT_CHANGED, ['from' => 'Old', 'to' => 'Printer jam'], 'Jane Doe changed the subject to “Printer jam”.'],
    'status' => [TicketEventType::STATUS_CHANGED, ['from' => 'open', 'to' => 'on_hold'], 'Jane Doe changed the status to On hold.'],
    'priority' => [TicketEventType::PRIORITY_CHANGED, ['from' => 'normal', 'to' => 'high'], 'Jane Doe changed the priority to High.'],
    'due date set' => [TicketEventType::DUE_DATE_CHANGED, ['from' => null, 'to' => '2026-11-02T15:00:00+00:00'], 'Jane Doe set the due date to Mon, Nov 2, 2026 3:00 PM.'],
    'due date removed' => [TicketEventType::DUE_DATE_CHANGED, ['from' => '2026-11-02T15:00:00+00:00', 'to' => null], 'Jane Doe removed the due date.'],
    'desk' => [TicketEventType::DESK_CHANGED, ['from' => 'North', 'to' => 'South'], 'Jane Doe moved the ticket to South.'],
    'sla policy set' => [TicketEventType::SLA_POLICY_CHANGED, ['from' => null, 'to' => 'slp_x', 'to_name' => 'Gold'], 'Jane Doe set the ticket’s SLA policy to Gold.'],
    'sla policy removed' => [TicketEventType::SLA_POLICY_CHANGED, ['from' => 'slp_x', 'to' => null], 'Jane Doe removed the ticket’s SLA policy.'],
    'custom field set' => [TicketEventType::CUSTOM_FIELD_CHANGED, ['name' => 'Order number', 'from' => null, 'to' => 'ACME-42'], 'Jane Doe set Order number to ACME-42.'],
    'custom field cleared' => [TicketEventType::CUSTOM_FIELD_CHANGED, ['name' => 'Order number', 'from' => 'ACME-42', 'to' => null], 'Jane Doe cleared Order number.'],
    'assigned' => [TicketEventType::ASSIGNEE_ADDED, ['user_id' => 'usr_x', 'name' => 'John Smith'], 'Jane Doe assigned John Smith.'],
    'unassigned' => [TicketEventType::ASSIGNEE_REMOVED, ['user_id' => 'usr_x', 'name' => 'John Smith'], 'Jane Doe unassigned John Smith.'],
    'tag added' => [TicketEventType::TAG_ADDED, ['name' => 'billing'], 'Jane Doe added the tag “billing”.'],
    'tag removed' => [TicketEventType::TAG_REMOVED, ['name' => 'billing'], 'Jane Doe removed the tag “billing”.'],
    'reply' => [TicketEventType::COMMENT_ADDED, ['comment_id' => 'cmt_x'], 'Jane Doe replied.'],
    'note' => [TicketEventType::INTERNAL_NOTE_ADDED, ['comment_id' => 'cmt_x'], 'Jane Doe added an internal note.'],
    'comment edited' => [TicketEventType::COMMENT_EDITED, ['comment_id' => 'cmt_x'], 'Jane Doe edited a comment.'],
    'comment deleted' => [TicketEventType::COMMENT_DELETED, ['comment_id' => 'cmt_x'], 'Jane Doe deleted a comment.'],
    'attached' => [TicketEventType::ATTACHMENT_ADDED, ['attachment_id' => 'att_x', 'filename' => 'invoice.pdf'], 'Jane Doe attached invoice.pdf.'],
    'detached' => [TicketEventType::ATTACHMENT_REMOVED, ['attachment_id' => 'att_x', 'filename' => 'invoice.pdf'], 'Jane Doe removed the attachment invoice.pdf.'],
    'reminder' => [TicketEventType::REMINDER_SET, ['remind_at' => '2026-11-02T15:00:00+00:00'], 'Jane Doe set a reminder for Mon, Nov 2, 2026 3:00 PM.'],
    'sla warning' => [TicketEventType::SLA_WARNING, ['metric' => 'first_response', 'due_at' => '2026-11-02T15:00:00+00:00'], 'First response SLA is at risk: due Mon, Nov 2, 2026 3:00 PM.'],
    'sla breached' => [TicketEventType::SLA_BREACHED, ['metric' => 'resolution', 'due_at' => '2026-11-02T15:00:00+00:00'], 'Resolution SLA was breached.'],
    'deleted' => [TicketEventType::DELETED, [], 'Jane Doe deleted the ticket.'],
    'restored' => [TicketEventType::RESTORED, [], 'Jane Doe restored the ticket.'],
]);

test('events name contacts, harbor itself, and deleted users', function () {
    $ticket = Ticket::factory()->create();
    $contact = Contact::factory()->create(['name' => 'Pat Lee']);
    $user = User::factory()->create();
    $byUser = $ticket->recordEvent(TicketEventType::CREATED, $user);
    $user->delete();

    expect($ticket->recordEvent(TicketEventType::CREATED, $contact)->description())->toBe('Pat Lee created a ticket.');
    expect($ticket->recordEvent(TicketEventType::CREATED, null)->description())->toBe('Harbor created a ticket.');
    expect($byUser->fresh()->description())->toBe('A deleted user created a ticket.');
});

test('events cannot be changed or deleted', function () {
    $event = Ticket::factory()->create()->recordEvent(TicketEventType::CREATED, null);

    expect(fn () => $event->update(['type' => TicketEventType::DELETED]))->toThrow(LogicException::class);
    expect(fn () => $event->delete())->toThrow(LogicException::class);
    expect($event->fresh()->type)->toBe(TicketEventType::CREATED);
});
