<?php

use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'services.postmark.token' => 'platform-token']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->mailbox = $this->acme->execute(fn () => Mailbox::factory()->postmark()->create());
});

/**
 * The sample Postmark inbound payload.
 *
 * @return array<string, mixed>
 */
function postmarkMailboxInbound(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/emails/postmark-inbound.json'), true);
}

test('inbound mail posted to a mailbox webhook URL opens a ticket in its workspace', function () {
    Mail::fake();
    $this->postJson(route('webhooks.postmark.mailbox', [$this->mailbox->id, 'webhook-secret']), postmarkMailboxInbound())->assertOk();

    expect(Ticket::withoutGlobalScope('workspace')->sole())
        ->workspace_id->toBe($this->acme->id)
        ->mailbox_id->toBe($this->mailbox->id)
        ->subject->toBe('Printer jam');
});

test("a wrong token, or another workspace's token, is rejected", function () {
    $other = Workspace::factory()->create()->execute(fn () => Mailbox::factory()->postmark()->create(['push_secret' => 'other-secret']));

    $this->postJson(route('webhooks.postmark.mailbox', [$this->mailbox->id, 'nope']), postmarkMailboxInbound())->assertNotFound();
    $this->postJson(route('webhooks.postmark.mailbox', [$this->mailbox->id, 'other-secret']), postmarkMailboxInbound())->assertNotFound();
    $this->postJson(route('webhooks.postmark.mailbox', [$other->id, 'webhook-secret']), postmarkMailboxInbound())->assertNotFound();

    expect(Ticket::withoutGlobalScope('workspace')->count())->toBe(0);
});

test("Postmark's test payload without raw email is accepted and ignored", function () {
    $this->postJson(route('webhooks.postmark.mailbox', [$this->mailbox->id, 'webhook-secret']), [...postmarkMailboxInbound(), 'RawEmail' => ''])->assertOk();

    expect(Ticket::withoutGlobalScope('workspace')->count())->toBe(0);
});

test("replies go out through the workspace's Postmark server token, never the platform's", function () {
    $transport = $this->acme->execute(fn () => $this->mailbox->transport());

    expect($transport)->toBeInstanceOf(PostmarkApiTransport::class)
        ->and((fn () => $this->key)->call($transport))->toBe('server-token');
});
