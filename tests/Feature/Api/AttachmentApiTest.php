<?php

use App\Enums\WorkspaceRole;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake('local');
});

test('agents upload, list, download and remove files', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    $ticket = Ticket::factory()->for($desk)->create();
    actAs($agent, $workspace);

    $upload = $this->post("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/attachments", [
        'file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello'),
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('filename', 'notes.txt')
        ->assertJsonPath('size', 5);

    $this->getJson("http://acme.harbor.test/api/v1/tickets/{$ticket->id}/attachments")->assertJsonCount(1, 'data');

    $download = $this->get($upload->json('download_url'));
    $download->assertOk()->assertDownload('notes.txt');
    expect($download->streamedContent())->toBe('hello');

    $this->deleteJson("http://acme.harbor.test/api/v1/attachments/{$upload->json('id')}")->assertNoContent();
    expect(Attachment::count())->toBe(0);
});

test('uploads need a file', function () {
    ['workspace' => $workspace, 'desk' => $desk, 'agent' => $agent] = acmeWithAgent();
    actAs($agent, $workspace);

    $this->postJson('http://acme.harbor.test/api/v1/tickets/'.Ticket::factory()->for($desk)->create()->id.'/attachments', [])
        ->assertJsonValidationErrors(['file' => 'The file field is required.']);
});

test('only people who can see the ticket can download its files', function () {
    ['workspace' => $workspace, 'desk' => $desk] = acmeWithAgent();
    $attachment = Attachment::factory()->for(Ticket::factory()->for($desk))->create();
    $bystander = User::factory()->create();
    $workspace->users()->attach($bystander, ['role' => WorkspaceRole::AGENT]);
    actAs($bystander, $workspace);

    $this->get("http://acme.harbor.test/api/v1/attachments/{$attachment->id}/download")->assertForbidden();
});

test('another workspace files cannot be downloaded', function () {
    ['workspace' => $workspace, 'agent' => $agent] = acmeWithAgent();
    $foreign = Workspace::factory()->create()->execute(fn () => Attachment::factory()->create());
    $workspace->makeCurrent();
    actAs($agent, $workspace);

    $this->get("http://acme.harbor.test/api/v1/attachments/{$foreign->id}/download")->assertNotFound();
});
