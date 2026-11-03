<?php

use App\Actions\Exports\RequestContactExport;
use App\Enums\AuditAction;
use App\Enums\ExportStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\BuildExport;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\Export;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ExportReadyNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake(config('harbor.attachments.disk'));
    Notification::fake();

    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->contact = Contact::factory()->create(['name' => 'Cora Contact', 'email' => 'cora@example.com']);
    $this->ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'requester_id' => $this->contact->id]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->otherAdmin = User::factory()->create();
    $this->other->users()->attach($this->otherAdmin, ['role' => WorkspaceRole::ADMIN]);
    $this->workspace->makeCurrent();

    $this->web = 'http://acme.harbor.test';
    $this->api = 'http://acme.harbor.test/api/v1';
});

/**
 * Read an export's zip into a map of entry name to contents.
 *
 * @return array<string, string>
 */
function exportEntries(Export $export): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk(config('harbor.attachments.disk'))->path($export->path));
    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }

    return $entries;
}

test('the export zip holds the contact, their tickets, public comments, ratings and attachments only', function () {
    $public = TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'body' => 'Public reply']);
    $internal = TicketComment::factory()->internal()->create(['ticket_id' => $this->ticket->id, 'body' => 'Secret note']);
    SatisfactionRating::factory()->create(['ticket_id' => $this->ticket->id]);

    $disk = Storage::disk(config('harbor.attachments.disk'));
    $disk->put('att/public.txt', 'public file');
    $disk->put('att/internal.txt', 'internal file');
    $file = Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => $public->id, 'path' => 'att/public.txt', 'filename' => 'public.txt']);
    Attachment::factory()->create(['ticket_id' => $this->ticket->id, 'ticket_comment_id' => $internal->id, 'path' => 'att/internal.txt', 'filename' => 'internal.txt']);

    $otherContact = Contact::factory()->create(['email' => 'someone@example.com']);
    $otherTicket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'requester_id' => $otherContact->id]);
    TicketComment::factory()->create(['ticket_id' => $otherTicket->id, 'body' => 'Someone else']);

    $this->other->execute(function () {
        $contact = Contact::factory()->create(['email' => 'cora@example.com']);
        Ticket::factory()->create(['desk_id' => Workspace::current()->desks()->first()->id, 'requester_id' => $contact->id, 'subject' => 'Other workspace ticket']);
    });
    $this->workspace->makeCurrent();

    $export = (new RequestContactExport)->handle($this->admin, $this->contact);
    $export->refresh();
    $entries = exportEntries($export);

    expect($export->status)->toBe(ExportStatus::COMPLETED)
        ->and($export->path)->toBe("{$this->workspace->id}/exports/{$export->id}.zip")
        ->and($export->size)->toBeGreaterThan(0)
        ->and($export->expires_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and(array_keys($entries))->toEqualCanonicalizing(['contact.jsonl', 'tickets.jsonl', 'comments.jsonl', 'ratings.jsonl', 'attachments.jsonl', "attachments/{$file->id}/public.txt"])
        ->and($entries['contact.jsonl'])->toContain('cora@example.com')
        ->and($entries['tickets.jsonl'])->toContain($this->ticket->id)->not->toContain($otherTicket->id)->not->toContain('Other workspace ticket')
        ->and($entries['comments.jsonl'])->toContain('Public reply')->not->toContain('Secret note')->not->toContain('Someone else')
        ->and(substr_count($entries['ratings.jsonl'], "\n"))->toBe(1)
        ->and($entries["attachments/{$file->id}/public.txt"])->toBe('public file');

    Notification::assertSentTo($this->admin, ExportReadyNotification::class);
});

test('requesting an export records an audit event and downloading one does too', function () {
    $export = (new RequestContactExport)->handle($this->admin, $this->contact);

    $event = AuditEvent::query()->where('action', AuditAction::CONTACT_EXPORT_REQUESTED)->sole();
    expect($event->actor_id)->toBe($this->admin->id)
        ->and($event->subject_id)->toBe($this->contact->id)
        ->and($event->properties)->toBe(['export_id' => $export->id]);

    actAs($this->admin, $this->workspace);
    $this->get("$this->api/exports/$export->id/download")->assertOk();

    expect(AuditEvent::query()->where('action', AuditAction::EXPORT_DOWNLOADED)->sole()->properties)->toBe(['export_id' => $export->id]);
});

test('the browser download needs a valid signature and an admin or the requester', function () {
    $export = (new RequestContactExport)->handle($this->admin, $this->contact);
    $url = $export->signedDownloadUrl();

    $this->get("$this->web/exports/$export->id/download")->assertRedirect();
    $this->get($url)->assertRedirect();

    $this->actingAs($this->admin);
    $this->get("$this->web/exports/$export->id/download")->assertForbidden();
    $this->get($url)->assertOk()->assertDownload("harbor-export-$export->id.zip");

    $this->actingAs($this->agent)->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

    // A requester who is no longer an admin can still fetch their own export.
    $this->workspace->users()->updateExistingPivot($this->admin->id, ['role' => WorkspaceRole::AGENT]);
    $this->actingAs($this->admin)->get($url)->assertOk();
});

test('expired and unfinished exports cannot be downloaded through the API', function () {
    $export = (new RequestContactExport)->handle($this->admin, $this->contact);
    actAs($this->admin, $this->workspace);

    $export->update(['expires_at' => now()->subMinute()]);
    $this->get("$this->api/exports/$export->id/download")->assertStatus(410);

    $export->update(['expires_at' => now()->addDay(), 'status' => ExportStatus::PENDING, 'path' => null]);
    $this->get("$this->api/exports/$export->id/download")->assertNotFound();
});

test('pruning removes expired exports and their files across workspaces', function () {
    $disk = Storage::disk(config('harbor.attachments.disk'));
    $old = (new RequestContactExport)->handle($this->admin, $this->contact);
    $fresh = (new RequestContactExport)->handle($this->admin, $this->contact);
    $old->refresh()->update(['expires_at' => now()->subDay()]);
    $fresh->refresh();

    Workspace::forgetCurrent();
    $this->artisan('model:prune', ['--model' => Export::class])->assertSuccessful();
    $this->workspace->makeCurrent();

    expect(Export::query()->pluck('id')->all())->toBe([$fresh->id])
        ->and($disk->exists($old->path))->toBeFalse()
        ->and($disk->exists($fresh->path))->toBeTrue();
});

test('admins request exports and list them on the web and the API', function () {
    $this->actingAs($this->admin)->post("$this->web/contacts/{$this->contact->id}/export")->assertRedirect();

    $export = Export::sole();
    expect($export->requested_by)->toBe($this->admin->id);

    $this->get("$this->web/workspace/data-privacy")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('workspace/DataPrivacy')
        ->has('exports.data', 1)
        ->where('exports.data.0.subject_name', 'Cora Contact')
        ->where('exports.data.0.status', 'completed')
        ->whereNot('exports.data.0.download_url', null));

    $this->get("$this->web/contacts/{$this->contact->id}")->assertInertia(fn (Assert $page) => $page->where('canExport', true));

    actAs($this->admin, $this->workspace);
    $this->postJson("$this->api/contacts/{$this->contact->id}/export")->assertCreated()->assertJsonPath('type', 'contact')->assertJsonPath('subject_id', $this->contact->id);
    $this->getJson("$this->api/exports")->assertOk()->assertJsonCount(2, 'data');
});

test('agents cannot request, list or download exports', function () {
    $export = (new RequestContactExport)->handle($this->admin, $this->contact);

    $this->actingAs($this->agent);
    $this->post("$this->web/contacts/{$this->contact->id}/export")->assertForbidden();
    $this->get("$this->web/workspace/data-privacy")->assertForbidden();
    $this->get("$this->web/contacts/{$this->contact->id}")->assertInertia(fn (Assert $page) => $page->where('canExport', false));

    actAs($this->agent, $this->workspace);
    $this->postJson("$this->api/contacts/{$this->contact->id}/export")->assertForbidden();
    $this->getJson("$this->api/exports")->assertForbidden();
    $this->get("$this->api/exports/$export->id/download")->assertForbidden();
    expect(Export::count())->toBe(1);
});

test('another workspace cannot list, request or download these exports', function () {
    $export = (new RequestContactExport)->handle($this->admin, $this->contact);
    $otherApi = 'http://other.harbor.test/api/v1';

    actAs($this->otherAdmin, $this->other);
    $this->getJson("$otherApi/exports")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("$otherApi/exports/$export->id/download")->assertNotFound();
    $this->postJson("$otherApi/contacts/{$this->contact->id}/export")->assertNotFound();

    $this->actingAs($this->otherAdmin);
    $this->get('http://other.harbor.test/workspace/data-privacy')->assertInertia(fn (Assert $page) => $page->has('exports.data', 0));
    $this->post("http://other.harbor.test/contacts/{$this->contact->id}/export")->assertNotFound();

    $signed = str_replace('acme', 'other', $export->signedDownloadUrl());
    $this->get($signed)->assertNotFound();

    expect(Export::withoutGlobalScopes()->count())->toBe(1);
});

test('the job refuses to read a contact from another workspace', function () {
    $export = (new RequestContactExport)->handle($this->admin, $this->contact);
    $foreign = $this->other->execute(fn () => Export::create([
        'type' => 'contact', 'subject_id' => $this->contact->id, 'requested_by' => $this->otherAdmin->id,
        'status' => ExportStatus::PENDING, 'expires_at' => now()->addDay(),
    ]));

    $this->other->execute(fn () => expect(fn () => (new BuildExport($foreign))->handle())->toThrow(ModelNotFoundException::class));
    expect($foreign->fresh()?->path)->toBeNull();
});
