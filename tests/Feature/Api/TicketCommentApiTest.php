<?php

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

    $commentId = $this->post("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/comments", [
        'body' => 'See attached.',
        'is_internal' => '1',
        'attachments' => [UploadedFile::fake()->create('log.txt')],
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.is_internal', true)
        ->assertJsonPath('data.author_name', 'Jane Doe')
        ->assertJsonPath('data.attachments.0.filename', 'log.txt')
        ->json('data.id');

    $this->patchJson("http://acme.harbor.test/api/v1/comments/{$commentId}", ['body' => 'See the attached log.'])
        ->assertOk()
        ->assertJsonPath('data.body', 'See the attached log.');

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
