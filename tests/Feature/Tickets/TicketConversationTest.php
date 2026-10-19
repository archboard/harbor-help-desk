<?php

use App\Models\Attachment;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->ticket = Ticket::factory()->for($this->desk)->create();
});

test('agents reply and add internal notes with files', function () {
    $this->actingAs($this->agent);

    $this->post(route('tickets.comments.store', $this->ticket), [
        'body' => 'Try restarting it.',
        'attachments' => [UploadedFile::fake()->createWithContent('steps.txt', 'one two')],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->post(route('tickets.comments.store', $this->ticket), ['body' => 'Customer seems upset.', 'is_internal' => '1'])
        ->assertSessionHasNoErrors();

    [$reply, $note] = $this->ticket->comments()->with('attachments')->get();
    expect($reply)->is_internal->toBeFalse()->author_id->toBe($this->agent->id)
        ->and($reply->attachments->sole()->filename)->toBe('steps.txt')
        ->and($note->is_internal)->toBeTrue();

    $this->post(route('tickets.comments.store', $this->ticket), ['body' => ''])->assertSessionHasErrors('body');
});

test('the ticket page links files to the web download route', function () {
    $this->actingAs($this->agent)
        ->post(route('tickets.attachments.store', $this->ticket), ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')])
        ->assertSessionHasNoErrors();
    $attachment = Attachment::sole();

    $this->actingAs($this->agent)
        ->get(route('tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attachments.0.download_url', route('attachments.download', $attachment)));
});

test('desk members download files and others are forbidden', function () {
    $this->actingAs($this->agent)
        ->post(route('tickets.attachments.store', $this->ticket), ['file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello')]);
    $attachment = Attachment::sole();

    $this->actingAs($this->agent)->get(route('attachments.download', $attachment))->assertOk()->assertDownload('notes.txt');

    $outsider = deskMember($this->workspace, Desk::factory()->create());
    $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
});

test('uploaders remove their own files but not other people\'s', function () {
    $mine = Attachment::factory()->for($this->ticket)->create(['uploader_type' => 'user', 'uploader_id' => $this->agent->id]);
    $theirs = Attachment::factory()->for($this->ticket)->create(['uploader_type' => 'user', 'uploader_id' => User::factory()->create()->id]);
    $this->actingAs($this->agent);

    $this->delete(route('attachments.destroy', $theirs))->assertForbidden();
    $this->delete(route('attachments.destroy', $mine))->assertRedirect();

    expect(Attachment::pluck('id')->all())->toBe([$theirs->id]);
});

test('authors edit and delete their own comments only', function () {
    $mine = TicketComment::factory()->for($this->ticket)->create(['author_id' => $this->agent->id, 'body' => 'Original']);
    $theirs = TicketComment::factory()->for($this->ticket)->create(['body' => 'Not yours']);
    $this->actingAs($this->agent);

    $this->patch(route('comments.update', $mine), ['body' => 'Edited'])->assertRedirect();
    expect($mine->fresh())->body->toBe('Edited')->edited_at->not->toBeNull();

    $this->patch(route('comments.update', $theirs), ['body' => 'Hijacked'])->assertForbidden();
    $this->delete(route('comments.destroy', $theirs))->assertForbidden();
    expect($theirs->fresh()->body)->toBe('Not yours');

    $this->delete(route('comments.destroy', $mine))->assertRedirect();
    expect($mine->fresh()->trashed())->toBeTrue();
});

test("another workspace's comments and files are not found", function () {
    [$comment, $attachment] = Workspace::factory()->create()->execute(function () {
        $ticket = Ticket::factory()->create();

        return [TicketComment::factory()->for($ticket)->create(), Attachment::factory()->for($ticket)->create()];
    });
    $this->workspace->makeCurrent();
    $this->actingAs($this->agent);

    $this->patch(route('comments.update', $comment->id), ['body' => 'x'])->assertNotFound();
    $this->get(route('attachments.download', $attachment->id))->assertNotFound();
    $this->delete(route('attachments.destroy', $attachment->id))->assertNotFound();
});
