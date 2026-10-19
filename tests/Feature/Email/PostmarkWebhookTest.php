<?php

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\Workspace;

beforeEach(function () {
    config([
        'harbor.multitenant' => true,
        'harbor.mail.inbound_domain' => 'in.harbor-help.test',
        'harbor.mail.postmark_webhook_secret' => 'secret',
    ]);
});

/**
 * The sample Postmark inbound payload, addressed to the given hosted address.
 *
 * @return array<string, mixed>
 */
function postmarkInbound(string $to = 'acme@in.harbor-help.test'): array
{
    $payload = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/emails/postmark-inbound.json'), true);

    return [...$payload, 'OriginalRecipient' => $to, 'ToFull' => [['Email' => $to, 'Name' => '']]];
}

test('requests without the right secret are rejected', function (?string $password, int $status) {
    $request = $password === null ? $this : $this->withBasicAuth('postmark', $password);

    $request->postJson(route('webhooks.postmark.inbound'), postmarkInbound())->assertStatus($status);
})->with([
    'no credentials' => [null, 401],
    'wrong secret' => ['nope', 401],
]);

test('the webhooks are off without a configured secret', function () {
    config(['harbor.mail.postmark_webhook_secret' => null]);

    $this->withBasicAuth('postmark', '')->postJson(route('webhooks.postmark.inbound'), postmarkInbound())->assertNotFound();
});

test('inbound mail opens a ticket in the workspace named by the address, plus tags included', function (string $to) {
    $acme = Workspace::factory()->create(['subdomain' => 'acme']);
    Workspace::factory()->create(['subdomain' => 'globex']);

    $this->withBasicAuth('postmark', 'secret')->postJson(route('webhooks.postmark.inbound'), postmarkInbound($to))->assertOk();

    $ticket = Ticket::withoutGlobalScope('workspace')->sole();
    expect($ticket->workspace_id)->toBe($acme->id)
        ->and($ticket->subject)->toBe('Printer jam');
    expect(Workspace::current())->toBeNull();
})->with(['acme@in.harbor-help.test', 'Acme+billing@in.harbor-help.test']);

test('mail for an unknown address is accepted and ignored', function () {
    Workspace::factory()->create(['subdomain' => 'acme']);

    $this->withBasicAuth('postmark', 'secret')->postJson(route('webhooks.postmark.inbound'), postmarkInbound('nobody@in.harbor-help.test'))->assertOk();
    $this->withBasicAuth('postmark', 'secret')->postJson(route('webhooks.postmark.inbound'), postmarkInbound('acme@elsewhere.test'))->assertOk();

    expect(Ticket::withoutGlobalScope('workspace')->count())->toBe(0);
});

test('self-hosted, any address on the inbound domain reaches the single workspace', function () {
    config(['harbor.multitenant' => false]);
    $workspace = Workspace::factory()->create();

    $this->withBasicAuth('postmark', 'secret')->postJson(route('webhooks.postmark.inbound'), postmarkInbound('support@in.harbor-help.test'))->assertOk();

    expect(Ticket::withoutGlobalScope('workspace')->sole()->workspace_id)->toBe($workspace->id);
});

test('a bounce adds an event to the ticket of the reply in its metadata', function () {
    $workspace = Workspace::factory()->create();
    $comment = $workspace->execute(fn () => TicketComment::factory()->create());

    $this->withBasicAuth('postmark', 'secret')->postJson(route('webhooks.postmark.bounce'), [
        'RecordType' => 'Bounce',
        'Type' => 'HardBounce',
        'Email' => 'nobody@example.org',
        'Description' => 'The server was unable to deliver your message.',
        'Metadata' => ['workspace_id' => $workspace->id, 'comment_id' => $comment->id],
    ])->assertOk();

    expect(TicketEvent::withoutGlobalScope('workspace')->sole())
        ->ticket_id->toBe($comment->ticket_id)
        ->properties->toEqual(['comment_id' => $comment->id, 'reason' => 'nobody@example.org: The server was unable to deliver your message.']);
});

test('a bounce cannot reach a comment in another workspace', function () {
    $workspaceA = Workspace::factory()->create();
    $comment = $workspaceA->execute(fn () => TicketComment::factory()->create());
    $workspaceB = Workspace::factory()->create();

    $this->withBasicAuth('postmark', 'secret')->postJson(route('webhooks.postmark.bounce'), [
        'Description' => 'Bounced',
        'Metadata' => ['workspace_id' => $workspaceB->id, 'comment_id' => $comment->id],
    ])->assertOk();

    expect(TicketEvent::withoutGlobalScope('workspace')->count())->toBe(0);
});
