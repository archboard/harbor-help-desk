<?php

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketAttachment;
use App\Enums\DeskRole;
use App\Models\Attachment;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(fn () => Storage::fake('local'));

test('uploads are stored under the ticket with their checksum and logged', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);

    $attachment = app(AddTicketAttachment::class)->handle($agent, $ticket, UploadedFile::fake()->createWithContent('invoice.pdf', 'PDF-BYTES'));

    expect($attachment->fresh())
        ->filename->toBe('invoice.pdf')
        ->size->toBe(9)
        ->checksum->toBe(hash('sha256', 'PDF-BYTES'))
        ->path->toBe("{$this->workspace->id}/tickets/{$ticket->id}/{$attachment->id}/invoice.pdf");
    expect(Storage::disk('local')->get($attachment->path))->toBe('PDF-BYTES');
    expect($ticket->events()->sole()->description())->toBe('Jane Doe attached invoice.pdf.');
});

test('path segments are stripped from uploaded filenames', function () {
    $ticket = Ticket::factory()->create();

    $attachment = app(AddTicketAttachment::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, UploadedFile::fake()->create('../../etc/passwd.txt'));

    expect($attachment->filename)->toBe('passwd.txt');
    expect($attachment->path)->toEndWith("/{$attachment->id}/passwd.txt");
});

test('blocked and oversized files are rejected', function (UploadedFile $file, string $message) {
    $ticket = Ticket::factory()->create();
    config(['harbor.attachments.max_size_kb' => 100]);

    expect(fn () => app(AddTicketAttachment::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, $file))
        ->toThrow(ValidationException::class, $message);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'blocked extension' => [fn () => UploadedFile::fake()->create('setup.exe', 1), 'Files of this type can’t be attached.'],
    'too large' => [fn () => UploadedFile::fake()->create('big.pdf', 101), 'The file field must not be greater than 100 kilobytes.'],
]);

test('a comment from another ticket cannot carry the upload', function () {
    $ticket = Ticket::factory()->create();
    $otherComment = TicketComment::factory()->for(Ticket::factory()->for($ticket->desk))->create();

    expect(fn () => app(AddTicketAttachment::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, UploadedFile::fake()->create('a.pdf'), $otherComment))
        ->toThrow(ValidationException::class);
});

test('comments can carry attachments', function () {
    $ticket = Ticket::factory()->create();

    $comment = app(AddTicketComment::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, [
        'body' => 'See attached.',
        'attachments' => [UploadedFile::fake()->create('a.pdf'), UploadedFile::fake()->create('b.png')],
    ]);

    expect($comment->attachments()->pluck('filename')->all())->toBe(['a.pdf', 'b.png']);
});

test('agents off the desk cannot upload', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => app(AddTicketAttachment::class)->handle(deskMember($this->workspace, Desk::factory()->create()), $ticket, UploadedFile::fake()->create('a.pdf')))
        ->toThrow(AuthorizationException::class);
});

test('uploaders can remove their file, which deletes it from the disk', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $attachment = app(AddTicketAttachment::class)->handle($agent, $ticket, UploadedFile::fake()->create('a.pdf'));

    app(DeleteTicketAttachment::class)->handle($agent, $attachment);

    expect(Attachment::find($attachment->id))->toBeNull();
    expect(Storage::disk('local')->exists($attachment->path))->toBeFalse();
    expect($ticket->events()->get()->last()->description())->toBe('Jane Doe removed the attachment a.pdf.');
});

test('agents cannot remove someone else file but desk leads can', function () {
    $ticket = Ticket::factory()->create();
    $attachment = app(AddTicketAttachment::class)->handle(deskMember($this->workspace, $ticket->desk), $ticket, UploadedFile::fake()->create('a.pdf'));

    expect(fn () => app(DeleteTicketAttachment::class)->handle(deskMember($this->workspace, $ticket->desk), $attachment))
        ->toThrow(AuthorizationException::class);

    app(DeleteTicketAttachment::class)->handle(deskMember($this->workspace, $ticket->desk, DeskRole::LEAD), $attachment);
    expect(Attachment::find($attachment->id))->toBeNull();
});

test('workspace b cannot read workspace a attachments', function () {
    $attachmentA = Attachment::factory()->create();
    Workspace::factory()->create()->makeCurrent();

    expect(Attachment::find($attachmentA->id))->toBeNull();
});
