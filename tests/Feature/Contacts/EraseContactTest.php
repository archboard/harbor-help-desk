<?php

use App\Actions\Contacts\EraseContact;
use App\Actions\Reports\BuildOverviewReport;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketMetric;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake(config('harbor.attachments.disk'));

    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->contact = Contact::factory()->create(['name' => 'Cora Contact', 'email' => 'cora@example.com']);
    $this->ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'requester_id' => $this->contact->id, 'subject' => 'My secret problem']);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->otherAdmin = User::factory()->create();
    $this->other->users()->attach($this->otherAdmin, ['role' => WorkspaceRole::ADMIN]);
    $this->workspace->makeCurrent();

    $this->web = 'http://acme.harbor.test';
    $this->api = 'http://acme.harbor.test/api/v1';
});

test('erasing scrubs the contact, their words and files but keeps tickets, agent replies and metrics', function () {
    $disk = Storage::disk(config('harbor.attachments.disk'));
    $disk->put('att/mine.txt', 'mine');
    $disk->put('att/agent.txt', 'agent');

    $mine = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'author_type' => 'contact', 'author_id' => $this->contact->id, 'body' => 'My phone is 555', 'message_id' => '<a@example.com>']);
    $reply = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'author_id' => $this->agent->id, 'body' => 'Agent reply']);
    $file = Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => $mine->id, 'uploader_type' => 'contact', 'uploader_id' => $this->contact->id, 'path' => 'att/mine.txt', 'filename' => 'passport.txt']);
    $agentFile = Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => $reply->id, 'uploader_id' => $this->agent->id, 'path' => 'att/agent.txt']);
    $rating = SatisfactionRating::factory()->create(['ticket_id' => $this->ticket->id, 'contact_id' => $this->contact->id, 'comment' => 'Cora here, thanks']);
    $metric = TicketMetric::factory()->create(['ticket_id' => $this->ticket->id, 'first_response_minutes' => 12]);

    $this->ticket->recordEvent(TicketEventType::SUBJECT_CHANGED, $this->agent, ['from' => 'My secret problem', 'to' => 'Renamed secret']);
    $this->ticket->recordEvent(TicketEventType::COMMENT_EDITED, $this->contact, ['comment_id' => $mine->id, 'from' => 'My phone is 554']);
    $this->ticket->recordEvent(TicketEventType::ATTACHMENT_ADDED, $this->contact, ['attachment_id' => $file->id, 'filename' => 'passport.txt']);
    $this->ticket->recordEvent(TicketEventType::ATTACHMENT_ADDED, $this->agent, ['attachment_id' => $agentFile->id, 'filename' => 'agent.txt']);
    $this->ticket->update(['subject' => 'Renamed secret']);

    $before = app(BuildOverviewReport::class)->handle($this->admin, [])->created->value;

    (new EraseContact)->handle($this->admin, $this->contact, ['confirm_email' => 'CORA@example.com']);

    expect($this->contact->fresh())
        ->name->toBe('Erased contact')
        ->email->toBe("erased+{$this->contact->id}@invalid")
        ->and($this->ticket->fresh()->subject)->toBe("Ticket #{$this->ticket->number}")
        ->and($mine->fresh())->body->toBe('[removed]')->message_id->toBeNull()
        ->and($reply->fresh()->body)->toBe('Agent reply')
        ->and(Attachment::find($file->id))->toBeNull()
        ->and($disk->exists('att/mine.txt'))->toBeFalse()
        ->and(Attachment::find($agentFile->id))->not->toBeNull()
        ->and($disk->exists('att/agent.txt'))->toBeTrue()
        ->and($rating->fresh()->comment)->toBeNull()
        ->and($rating->fresh()->rating)->toBe($rating->rating)
        ->and($metric->fresh()->first_response_minutes)->toBe(12)
        ->and(app(BuildOverviewReport::class)->handle($this->admin, [])->created->value)->toBe($before);

    $events = TicketEvent::query()->where('ticket_id', $this->ticket->id)->get()->keyBy(fn ($e) => $e->type->value.($e->actor_id === $this->agent->id ? '-agent' : ''));
    expect($events['subject_changed-agent']->properties)->toEqual(['from' => '[removed]', 'to' => "Ticket #{$this->ticket->number}"])
        ->and($events['comment_edited']->properties)->toEqual(['from' => '[removed]', 'comment_id' => $mine->id])
        ->and($events['attachment_added']->properties['filename'])->toBe('[removed]')
        ->and($events['attachment_added-agent']->properties['filename'])->toBe('agent.txt');
});

test('erasure is audited with ids only', function () {
    (new EraseContact)->handle($this->admin, $this->contact, ['confirm_email' => 'cora@example.com']);

    $event = AuditEvent::query()->where('action', AuditAction::CONTACT_ERASED)->sole();
    expect($event->actor_id)->toBe($this->admin->id)
        ->and($event->subject_id)->toBe($this->contact->id)
        ->and($event->properties)->toBe([])
        ->and(json_encode($event->getAttributes()))->not->toContain('cora')->not->toContain('Cora')->not->toContain('secret');
});

test('the confirmation must match the contact email and nothing is changed otherwise', function () {
    expect(fn () => (new EraseContact)->handle($this->admin, $this->contact, ['confirm_email' => 'wrong@example.com']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('confirm_email'));
    expect(fn () => (new EraseContact)->handle($this->admin, $this->contact, []))->toThrow(ValidationException::class);

    expect($this->contact->fresh()->email)->toBe('cora@example.com')
        ->and($this->ticket->fresh()->subject)->toBe('My secret problem')
        ->and(AuditEvent::count())->toBe(0);
});

test('admins erase through the web and the API, and the page shows the danger zone only to them', function () {
    $this->actingAs($this->admin)->get("$this->web/contacts/{$this->contact->id}")->assertInertia(fn ($page) => $page->where('canErase', true));
    $this->post("$this->web/contacts/{$this->contact->id}/erase", ['confirm_email' => 'nope'])->assertSessionHasErrors('confirm_email');
    $this->post("$this->web/contacts/{$this->contact->id}/erase", ['confirm_email' => 'cora@example.com'])->assertRedirect();
    expect($this->contact->fresh()->name)->toBe('Erased contact');

    $second = Contact::factory()->create(['email' => 'two@example.com']);
    actAs($this->admin, $this->workspace);
    $this->postJson("$this->api/contacts/$second->id/erase", ['confirm_email' => 'two@example.com'])->assertSuccessful()->assertJsonPath('name', 'Erased contact');
    $this->postJson("$this->api/contacts/$second->id/erase", ['confirm_email' => 'wrong'])->assertUnprocessable();
});

test('agents cannot erase contacts', function () {
    $this->actingAs($this->agent);
    $this->get("$this->web/contacts/{$this->contact->id}")->assertInertia(fn ($page) => $page->where('canErase', false));
    $this->post("$this->web/contacts/{$this->contact->id}/erase", ['confirm_email' => 'cora@example.com'])->assertForbidden();

    actAs($this->agent, $this->workspace);
    $this->postJson("$this->api/contacts/{$this->contact->id}/erase", ['confirm_email' => 'cora@example.com'])->assertForbidden();

    expect($this->contact->fresh()->email)->toBe('cora@example.com');
});

test('another workspace cannot erase this contact and erasing here leaves its data alone', function () {
    $this->actingAs($this->otherAdmin);
    $this->post("http://other.harbor.test/contacts/{$this->contact->id}/erase", ['confirm_email' => 'cora@example.com'])->assertNotFound();
    actAs($this->otherAdmin, $this->other);
    $this->postJson('http://other.harbor.test/api/v1/contacts/'.$this->contact->id.'/erase', ['confirm_email' => 'cora@example.com'])->assertNotFound();
    expect($this->contact->fresh()->email)->toBe('cora@example.com');

    $theirs = $this->other->execute(function () {
        $contact = Contact::factory()->create(['name' => 'Cora Contact', 'email' => 'cora@example.com']);
        $ticket = Ticket::factory()->create(['desk_id' => Workspace::current()->desks()->first()->id, 'requester_id' => $contact->id, 'subject' => 'Their subject']);
        TicketComment::factory()->create(['ticket_id' => $ticket->id, 'author_type' => 'contact', 'author_id' => $contact->id, 'body' => 'Their words']);

        return $contact;
    });
    $this->workspace->makeCurrent();
    $theirAudits = $this->other->execute(fn () => AuditEvent::count());

    (new EraseContact)->handle($this->admin, $this->contact, ['confirm_email' => 'cora@example.com']);

    $this->other->execute(function () use ($theirs, $theirAudits) {
        expect($theirs->fresh())->name->toBe('Cora Contact')->email->toBe('cora@example.com')
            ->and(Ticket::sole()->subject)->toBe('Their subject')
            ->and(TicketComment::sole()->body)->toBe('Their words')
            ->and(AuditEvent::count())->toBe($theirAudits);
    });
});
