<?php

use App\Enums\NotificationType;
use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;
use App\Notifications\TicketActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    Storage::fake('local');
    $this->contact = Contact::factory()->create();
    $this->ticket = Ticket::factory()->create(['requester_id' => $this->contact->id]);
    $this->actingAs($this->contact, 'contact');
});

test('the list shows only the contact\'s tickets, across desks', function () {
    $otherDesk = Desk::factory()->create();
    $second = Ticket::factory()->create(['requester_id' => $this->contact->id, 'desk_id' => $otherDesk->id]);
    $foreign = Ticket::factory()->create();

    $this->get(route('portal.tickets.index'))->assertInertia(fn (Assert $page) => $page
        ->component('portal/tickets/Index')
        ->has('tickets.data', 2)
        ->where('tickets.data.0.id', $second->id)
        ->where('tickets.data.1.id', $this->ticket->id)
        ->missing('tickets.data.0.custom_fields'));

    expect($foreign->id)->not->toBe($second->id);
});

test('the list filters open and resolved tickets', function () {
    $solved = Ticket::factory()->create(['requester_id' => $this->contact->id, 'status' => TicketStatus::SOLVED]);
    $this->ticket->update(['status' => TicketStatus::OPEN]);

    $this->get(route('portal.tickets.index', ['status' => 'open']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $this->ticket->id));
    $this->get(route('portal.tickets.index', ['status' => 'resolved']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $solved->id));
});

test('another contact\'s ticket is not found', function () {
    $foreign = Ticket::factory()->create();

    $this->get(route('portal.tickets.show', $foreign))->assertNotFound();
    $this->post(route('portal.tickets.comments.store', $foreign), ['body' => 'Hi'])->assertNotFound();
    expect($foreign->comments()->count())->toBe(0);
});

test('internal notes and their files are hidden and not downloadable', function () {
    $public = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'body' => 'Public reply']);
    $note = TicketComment::factory()->internal()->create(['ticket_id' => $this->ticket->id, 'body' => 'Secret note']);
    $publicFile = Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => $public->id]);
    $noteFile = Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => $note->id]);
    Storage::disk($publicFile->disk)->put($publicFile->path, 'PUBLIC');
    Storage::disk($noteFile->disk)->put($noteFile->path, 'NOTE');

    $this->get(route('portal.tickets.show', $this->ticket))->assertInertia(fn (Assert $page) => $page
        ->component('portal/tickets/Show')
        ->has('comments', 1)
        ->where('comments.0.body', 'Public reply')
        ->where('comments.0.attachments.0.download_url', route('portal.attachments.download', $publicFile)));

    $this->get(route('portal.attachments.download', $publicFile))->assertOk();
    $this->get(route('portal.attachments.download', $noteFile))->assertNotFound();
});

test('files attached to the ticket without a comment are not downloadable', function () {
    $file = Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => null]);

    $this->get(route('portal.attachments.download', $file))->assertNotFound();
});

test('files on another contact\'s ticket are not downloadable', function () {
    $file = Attachment::factory()->create();

    $this->get(route('portal.attachments.download', $file))->assertNotFound();
});

test('a reply on a solved ticket reopens it and notifies agents', function () {
    Notification::fake();
    $agent = deskMember($this->workspace, $this->ticket->desk);
    $this->ticket->update(['status' => TicketStatus::SOLVED]);

    $this->post(route('portal.tickets.comments.store', $this->ticket), [
        'body' => 'Still broken.',
        'attachments' => [UploadedFile::fake()->createWithContent('shot.png', 'PNG')],
    ])->assertRedirect();

    $comment = $this->ticket->comments()->sole();
    expect($this->ticket->fresh()->status)->toBe(TicketStatus::OPEN)
        ->and($comment->body)->toBe('Still broken.')
        ->and($comment->attachments)->toHaveCount(1);
    Notification::assertSentTo($agent, TicketActivity::class, fn (TicketActivity $n) => $n->type === NotificationType::REQUESTER_REPLIED);
});

test('a reply needs a body', function () {
    $this->post(route('portal.tickets.comments.store', $this->ticket), ['body' => ''])->assertSessionHasErrors('body');
});

test('a contact cannot set internal notes through the portal', function () {
    $this->post(route('portal.tickets.comments.store', $this->ticket), ['body' => 'Hi', 'is_internal' => true])->assertRedirect();

    expect($this->ticket->comments()->sole()->is_internal)->toBeFalse();
});

test('the reply email links to the ticket in the portal only when it is enabled', function () {
    Mail::fake();
    $comment = TicketComment::factory()->create(['ticket_id' => $this->ticket->id]);
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $this->ticket->desk_id]);
    $link = $this->workspace->url("/portal/tickets/{$this->ticket->id}");

    $mail = new TicketReplyMail($comment, $mailbox, 'm@harbor.test');
    $mail->assertSeeInHtml($link)->assertSeeInText($link)->assertSeeInText('View this ticket online');

    $this->workspace->update(['settings' => ['portal_enabled' => false]]);
    (new TicketReplyMail($comment->fresh(), $mailbox, 'm@harbor.test'))->assertDontSeeInText('View this ticket online');
});

test('another workspace\'s tickets and files are out of reach', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    URL::forceRootUrl(null);
    $acme = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $contact = Contact::factory()->create(['email' => 'jane@example.com']);
    $ticket = Ticket::factory()->create(['requester_id' => $contact->id]);
    $foreign = $other->execute(function () {
        $theirs = Contact::factory()->create(['email' => 'jane@example.com']);
        $ticket = Ticket::factory()->create(['requester_id' => $theirs->id]);

        return [$ticket, Attachment::factory()->create(['ticket_id' => $ticket->id])];
    });
    $acme->makeCurrent();
    $this->actingAs($contact, 'contact');

    $this->get("http://acme.harbor.test/portal/tickets/{$foreign[0]->id}")->assertNotFound();
    $this->post("http://acme.harbor.test/portal/tickets/{$foreign[0]->id}/comments", ['body' => 'Hi'])->assertNotFound();
    $this->get("http://acme.harbor.test/portal/attachments/{$foreign[1]->id}/download")->assertNotFound();
    $this->get('http://acme.harbor.test/portal/tickets')->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.id', $ticket->id));
    expect($other->execute(fn () => TicketComment::count()))->toBe(0);
});
