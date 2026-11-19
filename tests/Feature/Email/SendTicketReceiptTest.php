<?php

use App\Actions\Desks\UpdateDesk;
use App\Actions\Tickets\CreateTicket;
use App\Enums\WorkspaceRole;
use App\Mail\TicketReceiptMail;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    Mail::fake();

    $this->desk = Desk::sole();
    $this->requester = Contact::factory()->create(['name' => 'Pat Lee', 'email' => 'pat@example.com']);
});

function openTicketByEmail(Contact $contact, Desk $desk, ?Mailbox $mailbox = null): void
{
    app(CreateTicket::class)->handle($contact, $desk, array_filter([
        'subject' => 'Printer jam',
        'requester_id' => $contact->id,
        'body' => 'It is jammed again.',
        'mailbox_id' => $mailbox?->id,
        'message_id' => $mailbox ? 'abc@mail.example.com' : null,
    ]));
}

test('a requester who opens a ticket gets a receipt threaded onto their email', function () {
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id, 'name' => 'Acme Support', 'address' => 'support@acme.com', 'signature' => 'The Acme team']);

    openTicketByEmail($this->requester, $this->desk, $mailbox);

    Mail::assertSent(TicketReceiptMail::class, function (TicketReceiptMail $mail) use ($mailbox): bool {
        $mail->assertSeeInText('##- Reply above this line -##')
            ->assertSeeInText('We received your request “Printer jam”')
            ->assertSeeInText('The Acme team');

        return $mail->hasTo('pat@example.com', 'Pat Lee')
            && $mail->hasFrom('support@acme.com', 'Acme Support')
            && $mail->hasSubject("Re: Printer jam [#{$mail->ticket->number}]")
            && $mail->headers()->references === ['abc@mail.example.com']
            && $mail->headers()->text['In-Reply-To'] === '<abc@mail.example.com>'
            && $mail->headers()->text['Auto-Submitted'] === 'auto-replied'
            && $mail->mailer === "mailbox-{$mailbox->id}";
    });
});

test('tickets opened by agents get no receipt', function () {
    Mailbox::factory()->create(['default_desk_id' => $this->desk->id]);

    app(CreateTicket::class)->handle(deskMember($this->workspace, $this->desk), $this->desk, [
        'subject' => 'Printer jam',
        'requester_id' => $this->requester->id,
        'body' => 'Logged from a phone call.',
    ]);

    Mail::assertNotSent(TicketReceiptMail::class);
});

test('a desk can turn receipts off', function () {
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $this->desk->id]);
    $admin = User::factory()->create();
    $this->workspace->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);

    app(UpdateDesk::class)->handle($admin, $this->desk, ['sends_receipts' => false]);
    openTicketByEmail($this->requester, $this->desk, $mailbox);

    expect($this->desk->fresh()->sends_receipts)->toBeFalse();
    Mail::assertNotSent(TicketReceiptMail::class);
});

test('no receipt goes out through another workspace mailbox', function () {
    Workspace::factory()->create()->execute(fn () => Mailbox::factory()->create());

    openTicketByEmail($this->requester, $this->desk);

    Mail::assertNotSent(TicketReceiptMail::class);
});
