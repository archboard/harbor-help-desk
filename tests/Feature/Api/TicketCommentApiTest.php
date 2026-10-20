<?php

use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake('local');
});

test('agents comment with attachments, edit and delete, and read the conversation', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    actAs($agent, $workspace);

    $response = $this->post("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'See attached.',
        'is_internal' => '1',
        'attachments' => [UploadedFile::fake()->createWithContent('log.txt', 'boot ok')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $comment = TicketComment::sole();
    $attachment = $comment->attachments()->sole();
    $response->assertExactJson([
        'id' => $comment->id,
        'ticket_id' => $ticket->id,
        'body' => 'See attached.',
        'is_internal' => true,
        'author_type' => 'user',
        'author_id' => $agent->id,
        'author_name' => 'Jane Doe',
        'attachments' => [[
            'id' => $attachment->id,
            'ticket_id' => $ticket->id,
            'ticket_comment_id' => $comment->id,
            'filename' => 'log.txt',
            'mime_type' => $attachment->mime_type,
            'size' => 7,
            'checksum' => $attachment->checksum,
            'uploader_type' => 'user',
            'uploader_id' => $agent->id,
            'download_url' => route('api.v1.attachments.download', $attachment),
            'created_at' => $attachment->created_at->toIso8601String(),
        ]],
        'edited_at' => null,
        'created_at' => $comment->created_at->toIso8601String(),
    ]);
    $commentId = $comment->id;

    $this->patchJson("http://acme.harbor.test/api/v1/comments/{$commentId}", ['body' => 'See the attached log.'])
        ->assertOk()
        ->assertJsonPath('body', 'See the attached log.');

    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.id', $commentId);

    $this->deleteJson("http://acme.harbor.test/api/v1/comments/{$commentId}")->assertNoContent();
    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/comments")->assertJsonCount(0, 'data');
});

test('comments need a body', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->postJson('http://acme.harbor.test/api/v1/tickets/'.Ticket::factory()->for($desk)->create()->id.'/comments', [])
        ->assertJsonValidationErrors(['body' => 'The body field is required.']);
});

test('another workspace comments are invisible', function () {
    ['workspace' => $workspace, 'agent' => $agent] = acmeWithAgent();
    $foreign = Workspace::factory()->create()->execute(fn () => TicketComment::factory()->create());
    $workspace->makeCurrent();
    actAs($agent, $workspace);

    $this->patchJson("http://acme.harbor.test/api/v1/comments/{$foreign->id}", ['body' => 'Hijacked'])->assertNotFound();
    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$foreign->ticket_id}/comments")->assertNotFound();
});

test('an internal note via the API mentions desk members and rejects others', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $colleague = deskMember($workspace, $desk);
    $outsider = deskMember($workspace, Desk::factory()->create());
    $ticket = Ticket::factory()->for($desk)->create();
    actAs($agent, $workspace);
    $url = "http://acme.harbor.test/api/v1/tickets/{$ticket->id}/comments";
    $headers = ['Accept' => 'application/json'];

    $this->post($url, ['body' => 'Ping', 'is_internal' => true, 'mentioned_user_ids' => [$outsider->id]], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('mentioned_user_ids.0');
    $this->post($url, ['body' => 'Ping', 'mentioned_user_ids' => [$colleague->id]], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('mentioned_user_ids');
    $this->post($url, ['body' => 'Ping', 'is_internal' => true, 'mentioned_user_ids' => [$colleague->id]], $headers)
        ->assertCreated();

    expect($colleague->notifications()->pluck('type')->all())->toBe(['mentioned']);
});
