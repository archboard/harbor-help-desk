<?php

use App\Actions\Tickets\AddTicketComment;
use App\Enums\MailboxDriver;
use App\Mail\TicketReplyMail;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    Mail::fake();
    config(['harbor.domain' => 'harbor.test']);

    $this->desk = Desk::sole();
    $this->mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id, 'name' => 'Acme Support', 'address' => 'support@acme.com', 'signature' => 'The Acme team']);
    $this->requester = Contact::factory()->create(['name' => 'Pat Lee', 'email' => 'pat@example.com']);
    $this->ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'requester_id' => $this->requester->id, 'subject' => 'Printer jam', 'mailbox_id' => $this->mailbox->id]);
    $this->agent = deskMember($this->workspace, $this->desk);
});

test('a public reply is emailed to the requester and its message id stored', function () {
    $comment = app(AddTicketComment::class)->handle($this->agent, $this->ticket, ['body' => 'Try turning it off and on again.']);

    expect($comment->fresh()->message_id)->toBe("{$comment->id}@harbor.test");
    Mail::assertSent(TicketReplyMail::class, function (TicketReplyMail $mail) use ($comment): bool {
        $mail->assertSeeInText('##- Reply above this line -##')
            ->assertSeeInText('Try turning it off and on again.')
            ->assertSeeInText('The Acme team')
            ->assertSeeInHtml('Ticket #'.$this->ticket->number);

        return $mail->hasTo('pat@example.com', 'Pat Lee')
            && $mail->hasFrom('support@acme.com', 'Acme Support')
            && $mail->hasSubject("Re: Printer jam [#{$this->ticket->number}]")
            && $mail->headers()->messageId === "{$comment->id}@harbor.test"
            && $mail->mailer === "mailbox-{$this->mailbox->id}";
    });
});

test('internal notes and contact replies are not emailed', function () {
    app(AddTicketComment::class)->handle($this->agent, $this->ticket, ['body' => 'Probably the toner.', 'is_internal' => true]);
    app(AddTicketComment::class)->handle($this->requester, $this->ticket, ['body' => 'Still broken.']);

    Mail::assertNothingSent();
});

test('replies thread onto the ticket earlier message ids', function () {
    TicketComment::factory()->for($this->ticket)->create(['message_id' => 'first@mail.example.com']);
    TicketComment::factory()->for($this->ticket)->create(['message_id' => 'second@mail.example.com']);
    $this->ticket->update(['subject' => 'Re: RE: Printer jam']);

    app(AddTicketComment::class)->handle($this->agent, $this->ticket, ['body' => 'On it.']);

    Mail::assertSent(TicketReplyMail::class, fn (TicketReplyMail $mail): bool => $mail->headers()->references === ['first@mail.example.com', 'second@mail.example.com']
        && $mail->headers()->text['In-Reply-To'] === '<second@mail.example.com>'
        && $mail->hasSubject("Re: Printer jam [#{$this->ticket->number}]"));
});

test('the hosted mailbox sends from its inbound address on the default mailer with Postmark metadata', function () {
    $this->mailbox->update(['driver' => MailboxDriver::HOSTED, 'inbound_address' => 'acme@in.harbor.test', 'settings' => null]);

    $comment = app(AddTicketComment::class)->handle($this->agent, $this->ticket, ['body' => 'On it.']);

    Mail::assertSent(TicketReplyMail::class, fn (TicketReplyMail $mail): bool => $mail->hasFrom('acme@in.harbor.test', 'Acme Support')
        && $mail->mailer !== "mailbox-{$this->mailbox->id}"
        && $mail->hasMetadata('workspace_id', $this->workspace->id)
        && $mail->hasMetadata('comment_id', $comment->id));
});

test('the reply files are attached', function () {
    Storage::fake(config('harbor.attachments.disk'));

    app(AddTicketComment::class)->handle($this->agent, $this->ticket, ['body' => 'See attached.', 'attachments' => [UploadedFile::fake()->createWithContent('steps.txt', 'Unplug it.')]]);

    $attachments = Mail::sent(TicketReplyMail::class)->sole()->attachments();
    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->as)->toBe('steps.txt');
});

test('a ticket without a mailbox uses the mailbox of its desk, then the oldest', function () {
    $otherDesk = Desk::factory()->create();
    $deskMailbox = Mailbox::factory()->create(['default_desk_id' => $otherDesk->id]);
    $ticket = Ticket::factory()->create(['desk_id' => $otherDesk->id, 'requester_id' => $this->requester->id]);
    $orphan = Ticket::factory()->create(['desk_id' => Desk::factory()->create()->id, 'requester_id' => $this->requester->id]);

    expect(Mailbox::forTicket($ticket)->is($deskMailbox))->toBeTrue()
        ->and(Mailbox::forTicket($orphan)->is($this->mailbox))->toBeTrue();
});

test('nothing is sent when the workspace has no mailbox', function () {
    $this->ticket->update(['mailbox_id' => null]);
    $this->mailbox->delete();

    $comment = app(AddTicketComment::class)->handle($this->agent, $this->ticket, ['body' => 'On it.']);

    Mail::assertNothingSent();
    expect($comment->fresh()->message_id)->toBeNull();
});
