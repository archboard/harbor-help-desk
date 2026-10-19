<?php

use App\Actions\Email\ReceiveInboundEmail;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Mail\Inbound\InboundEmail;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @param  array<string, mixed>  $overrides  constructor arguments of InboundEmail
 */
function inboundEmail(array $overrides = []): InboundEmail
{
    return new InboundEmail(...[
        'messageId' => fake()->uuid().'@mail.example.com',
        'inReplyTo' => null,
        'references' => [],
        'fromEmail' => 'pat@example.com',
        'fromName' => 'Pat Lee',
        'recipients' => ['support@acme.com'],
        'subject' => 'Printer jam',
        'text' => 'It is jammed again.',
        'attachments' => [],
        'headers' => [],
        ...$overrides,
    ]);
}

function receive(InboundEmail $email, ?Mailbox $mailbox = null): ?TicketComment
{
    return app(ReceiveInboundEmail::class)->handle($mailbox ?? Mailbox::sole(), $email);
}

beforeEach(function () {
    $this->desk = Desk::sole();
    $this->mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id, 'address' => 'support@acme.com']);
});

test('new mail opens a ticket on the default desk from a new contact', function () {
    $comment = receive(inboundEmail(['messageId' => 'first@mail.example.com']));

    $ticket = $comment->ticket;
    expect($ticket)
        ->subject->toBe('Printer jam')
        ->desk_id->toBe($this->desk->id)
        ->mailbox_id->toBe($this->mailbox->id);
    expect($ticket->requester->only('name', 'email'))->toBe(['name' => 'Pat Lee', 'email' => 'pat@example.com']);
    expect($comment)
        ->body->toBe('It is jammed again.')
        ->message_id->toBe('first@mail.example.com')
        ->author->is($ticket->requester)->toBeTrue();
});

test('a routing rule matching a recipient sends mail to its desk', function () {
    $billing = Desk::factory()->create();
    $this->mailbox->update(['routing' => [['address' => 'Billing@acme.com', 'desk_id' => $billing->id]]]);

    $comment = receive(inboundEmail(['recipients' => ['support@acme.com', 'billing@acme.com']]));

    expect($comment->ticket->desk_id)->toBe($billing->id);
});

test('a rule pointing at an archived desk falls back to the default desk', function () {
    $billing = Desk::factory()->create();
    $this->mailbox->update(['routing' => [['address' => 'billing@acme.com', 'desk_id' => $billing->id]]]);
    $billing->delete();

    expect(receive(inboundEmail(['recipients' => ['billing@acme.com']]))->ticket->desk_id)->toBe($this->desk->id);
});

test('replies thread by in-reply-to and reopen a solved ticket', function () {
    $first = receive(inboundEmail(['messageId' => 'first@mail.example.com']));
    $first->ticket->update(['status' => TicketStatus::SOLVED]);

    $reply = receive(inboundEmail(['inReplyTo' => 'first@mail.example.com', 'subject' => 'Re: Printer jam', 'text' => "Still broken.\n\nOn Mon, Pat wrote:\n> It is jammed again."]));

    expect($reply->ticket_id)->toBe($first->ticket_id)
        ->and($reply->body)->toBe('Still broken.')
        ->and($first->ticket->fresh()->status)->toBe(TicketStatus::OPEN)
        ->and(Ticket::count())->toBe(1);
    expect($first->ticket->events()->get()->last()->description())->toBe('Pat Lee changed the status to Open.');
});

test('replies thread by the ticket number in the subject when the requester sends them', function () {
    $ticket = receive(inboundEmail())->ticket;

    $reply = receive(inboundEmail(['subject' => "Re: Printer jam [#{$ticket->number}]", 'fromEmail' => 'PAT@example.com']));

    expect($reply->ticket_id)->toBe($ticket->id);
});

test('a stranger using a ticket number in the subject gets a new ticket', function () {
    $ticket = receive(inboundEmail())->ticket;

    $comment = receive(inboundEmail(['subject' => "Re: Printer jam [#{$ticket->number}]", 'fromEmail' => 'mallory@example.com']));

    expect($comment->ticket_id)->not->toBe($ticket->id);
});

test('the same message received twice is stored once', function () {
    receive(inboundEmail(['messageId' => 'same@mail.example.com']));

    expect(receive(inboundEmail(['messageId' => 'same@mail.example.com'])))->toBeNull();
    expect(TicketComment::count())->toBe(1);
});

test('auto-replies and mail from the workspace own mailboxes are dropped', function (array $overrides) {
    expect(receive(inboundEmail($overrides)))->toBeNull();
    expect(Ticket::count())->toBe(0);
})->with([
    'auto-reply' => [['headers' => ['auto-submitted' => 'auto-replied']]],
    'loop' => [['fromEmail' => 'support@acme.com']],
]);

test('a bounce of an emailed reply is logged on its ticket', function () {
    $ticket = Ticket::factory()->create();
    $comment = TicketComment::factory()->for($ticket)->create(['message_id' => 'cmt_abc@harbor.test']);

    $result = receive(inboundEmail(['fromEmail' => 'mailer-daemon@mx.example.net', 'references' => ['cmt_abc@harbor.test'], 'text' => 'Your message could not be delivered.']));

    expect($result)->toBeNull();
    expect($ticket->events()->sole())
        ->type->toBe(TicketEventType::EMAIL_BOUNCED)
        ->actor_type->toBeNull()
        ->properties->toEqual(['comment_id' => $comment->id, 'reason' => 'Your message could not be delivered.']);
    expect(TicketComment::count())->toBe(1);
});

test('new tickets per sender are rate limited but replies are not', function () {
    config(['harbor.mail.new_tickets_per_sender_per_hour' => 2]);

    $first = receive(inboundEmail(['messageId' => 'one@mail.example.com']));
    receive(inboundEmail());

    expect(receive(inboundEmail()))->toBeNull();
    expect(receive(inboundEmail(['inReplyTo' => 'one@mail.example.com'])))->not->toBeNull();
    expect(Ticket::count())->toBe(2);
});

test('attachments are stored and blocked ones are skipped with a note', function () {
    Storage::fake(config('harbor.attachments.disk'));

    $comment = receive(inboundEmail(['attachments' => [
        ['filename' => 'notes.txt', 'mime' => 'text/plain', 'contents' => 'hello'],
        ['filename' => 'virus.exe', 'mime' => 'application/octet-stream', 'contents' => 'MZ'],
    ]]));

    expect($comment->attachments->pluck('filename')->all())->toBe(['notes.txt']);
    expect($comment->body)->toBe("It is jammed again.\n\n[The attachment virus.exe was skipped.]");
    expect($comment->author)->toBeInstanceOf(Contact::class);
});

test('a mailbox in workspace b never threads into a workspace a ticket', function () {
    $commentA = receive(inboundEmail(['messageId' => 'a@mail.example.com']));
    $ticketA = $commentA->ticket;

    Workspace::factory()->create()->makeCurrent();
    $mailboxB = Mailbox::factory()->create(['default_desk_id' => Desk::sole()->id]);

    $commentB = receive(inboundEmail(['inReplyTo' => 'a@mail.example.com', 'subject' => "Re: [#{$ticketA->number}]"]), $mailboxB);

    expect($commentB->ticket->workspace_id)->not->toBe($ticketA->workspace_id);
    expect($ticketA->comments()->withoutGlobalScope('workspace')->count())->toBe(1);
});
