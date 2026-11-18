<?php

use App\Actions\Mailboxes\CreateMailbox;
use App\Enums\MailboxDriver;
use App\Enums\WorkspaceRole;
use App\Mail\Inbound\ImapMailboxClient;
use App\Mail\MailboxTestMail;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);

    $this->admin = User::factory()->create();
    $this->acme->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->desk = $this->acme->execute(fn () => Desk::sole());
});

/**
 * @return array<string, mixed>
 */
function imapMailboxInput(Desk $desk, array $overrides = []): array
{
    $server = ['host' => '1.1.1.1', 'port' => 993, 'encryption' => 'ssl', 'username' => 'help@acme.com', 'password' => 'hunter2'];

    return array_replace_recursive([
        'driver' => 'imap',
        'name' => 'Acme Support',
        'address' => 'help@acme.com',
        'default_desk_id' => $desk->id,
        'settings' => ['imap' => $server, 'smtp' => [...$server, 'port' => 587, 'encryption' => 'starttls']],
    ], $overrides);
}

test('admins connect, list, update and delete IMAP mailboxes without exposing passwords', function () {
    $billing = $this->acme->execute(fn () => Desk::factory()->create());
    actAs($this->admin, $this->acme);

    $response = $this->postJson('http://acme.harbor.test/api/v1/mailboxes', imapMailboxInput($this->desk, [
        'routing' => [['address' => 'billing@acme.com', 'desk_id' => $billing->id]],
    ]))->assertCreated();

    $mailbox = $this->acme->execute(fn () => Mailbox::sole());
    $response->assertExactJson([
        'id' => $mailbox->id,
        'driver' => 'imap',
        'driver_label' => MailboxDriver::IMAP->label(),
        'name' => 'Acme Support',
        'address' => 'help@acme.com',
        'inbound_address' => null,
        'default_desk_id' => $this->desk->id,
        'routing' => [['address' => 'billing@acme.com', 'desk_id' => $billing->id]],
        'signature' => null,
        'settings' => [
            'imap' => ['host' => '1.1.1.1', 'port' => 993, 'encryption' => 'ssl', 'username' => 'help@acme.com'],
            'smtp' => ['host' => '1.1.1.1', 'port' => 587, 'encryption' => 'starttls', 'username' => 'help@acme.com'],
        ],
        'receives_by' => 'poll',
        'polled_at' => null,
        'last_error' => null,
        'created_at' => $mailbox->created_at->toIso8601String(),
    ]);
    $id = $mailbox->id;

    $this->getJson('http://acme.harbor.test/api/v1/mailboxes')->assertOk()->assertJsonPath('0.id', $id);

    // Leaving the password out keeps it; a new IMAP server restarts polling from the newest mail.
    Mailbox::withoutGlobalScopes()->whereKey($id)->update(['imap_last_uid' => 42]);
    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$id}", ['name' => 'Help', 'settings' => ['imap' => ['host' => '8.8.8.8']]])
        ->assertOk()
        ->assertJsonPath('name', 'Help')
        ->assertJsonPath('settings.imap.host', '8.8.8.8');

    $mailbox = Mailbox::withoutGlobalScopes()->find($id);
    expect($mailbox->settings['imap']['password'])->toBe('hunter2')
        ->and($mailbox->imap_last_uid)->toBeNull();

    $this->deleteJson("http://acme.harbor.test/api/v1/mailboxes/{$id}")->assertNoContent();
    expect(Mailbox::withoutGlobalScopes()->count())->toBe(0);
});

test('an update may drop one server, but not both, and a new server needs every field', function () {
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create());
    $url = "http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}";
    actAs($this->admin, $this->acme);

    $this->patchJson($url, ['settings' => ['imap' => null, 'smtp' => null]])
        ->assertJsonValidationErrors(['settings' => 'A mailbox needs an IMAP or SMTP server.']);

    $this->patchJson($url, ['settings' => ['imap' => null]])->assertOk()->assertJsonPath('receives_by', 'never');

    $this->patchJson($url, ['settings' => ['smtp' => null]])->assertJsonValidationErrors('settings');

    $this->patchJson($url, ['settings' => ['imap' => ['host' => '1.1.1.1']]])
        ->assertJsonValidationErrors(['settings.imap.port', 'settings.imap.encryption', 'settings.imap.username', 'settings.imap.password']);

    $this->patchJson($url, ['settings' => ['imap' => ['host' => '1.1.1.1', 'port' => 993, 'encryption' => 'ssl', 'username' => 'help@acme.com', 'password' => 'hunter2']]])
        ->assertOk()
        ->assertJsonPath('settings.imap.host', '1.1.1.1');
});

test('in multitenant mode, mail servers must be public unless the operator allows private ones', function () {
    actAs($this->admin, $this->acme);
    $input = imapMailboxInput($this->acme->execute(fn () => Desk::sole()), [
        'settings' => ['imap' => null, 'smtp' => ['host' => '127.0.0.1', 'port' => 2525, 'encryption' => 'starttls']],
    ]);

    config(['harbor.mail.private_hosts' => false]);
    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', $input)->assertJsonValidationErrors('settings.smtp.host');

    config(['harbor.mail.private_hosts' => true]);
    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', $input)->assertCreated();
});

test('admins add a Postmark mailbox with a server token, and get its webhook URL but never the token', function () {
    config(['app.url' => 'https://harbor.test']);
    actAs($this->admin, $this->acme);

    $response = $this->postJson('http://acme.harbor.test/api/v1/mailboxes', [
        'driver' => 'postmark',
        'name' => 'Acme Support',
        'address' => 'help@acme.com',
        'default_desk_id' => $this->desk->id,
        'settings' => ['server_token' => 'pm-token'],
    ])->assertCreated();

    $mailbox = $this->acme->execute(fn () => Mailbox::sole());
    expect($mailbox->push_secret)->toHaveLength(40);
    $response->assertJsonPath('receives_by', 'webhook')
        ->assertJsonPath('webhook_url', "https://harbor.test/webhooks/postmark/mailboxes/{$mailbox->id}/{$mailbox->push_secret}")
        ->assertJsonMissingPath('settings')
        ->assertDontSee('pm-token');

    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}", ['settings' => ['server_token' => '', 'imap' => []]])
        ->assertJsonValidationErrors(['settings', 'settings.server_token']);
    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}", ['address' => 'support@acme.com', 'settings' => ['server_token' => 'new-token']])
        ->assertOk()
        ->assertJsonPath('address', 'support@acme.com');

    expect($mailbox->fresh()->settings)->toBe(['server_token' => 'new-token']);
});

test('OAuth mailboxes never expose their tokens or push secret', function () {
    $google = $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'settings' => ['access_token' => 'ya29.secret', 'refresh_token' => '1//secret'],
        'push_secret' => 'push-secret',
    ]));
    actAs($this->admin, $this->acme);

    $this->getJson("http://acme.harbor.test/api/v1/mailboxes/{$google->id}")
        ->assertOk()
        ->assertJsonMissingPath('settings')
        ->assertDontSee('secret');
});

test('OAuth mailboxes are reconnected with new tokens, and nothing else in their settings', function () {
    $google = $this->acme->execute(fn () => Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'settings' => ['access_token' => 'old', 'refresh_token' => 'old', 'expires_at' => 0],
        'last_error' => 'invalid_grant',
    ]));
    actAs($this->admin, $this->acme);

    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$google->id}", ['settings' => ['access_token' => 'new', 'imap' => ['host' => 'evil.test']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings', 'settings.refresh_token', 'settings.expires_at']);

    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$google->id}", ['settings' => ['access_token' => 'new', 'refresh_token' => 'new', 'expires_at' => 123]])
        ->assertOk()
        ->assertJsonPath('last_error', null);

    expect($google->fresh()->settings)->toBe(['access_token' => 'new', 'refresh_token' => 'new', 'expires_at' => 123]);
});

test('mailboxes are validated', function () {
    $archived = $this->acme->execute(fn () => Desk::factory()->create(['archived_at' => now()]));
    $foreignDesk = $this->other->execute(fn () => Desk::sole());
    actAs($this->admin, $this->acme);

    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', ['driver' => 'imap'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'address', 'default_desk_id', 'settings.imap', 'settings.smtp']);

    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', imapMailboxInput($archived, [
        'routing' => [['address' => 'not-an-email', 'desk_id' => $foreignDesk->id]],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_desk_id', 'routing.0.address', 'routing.0.desk_id']);

    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', imapMailboxInput($this->desk, ['driver' => 'hosted']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['driver']);
});

test('the hosted mailbox only changes its name, signature, desk and routing, and cannot be deleted', function () {
    $hosted = $this->acme->execute(fn () => Mailbox::factory()->create(['driver' => MailboxDriver::HOSTED, 'inbound_address' => 'acme@in.harbor.test', 'settings' => null]));
    actAs($this->admin, $this->acme);

    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$hosted->id}", ['signature' => 'Thanks!'])
        ->assertOk()
        ->assertJsonPath('signature', 'Thanks!');
    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$hosted->id}", ['address' => 'x@acme.com', 'settings' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address', 'settings']);
    $this->deleteJson("http://acme.harbor.test/api/v1/mailboxes/{$hosted->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mailbox']);
});

test('agents cannot see or manage mailboxes', function () {
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create());
    actAs($this->agent, $this->acme);

    $this->getJson('http://acme.harbor.test/api/v1/mailboxes')->assertForbidden();
    $this->getJson("http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}")->assertForbidden();
    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', imapMailboxInput($this->desk))->assertForbidden();
    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}", ['name' => 'Mine'])->assertForbidden();
    $this->deleteJson("http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}")->assertForbidden();
});

test('servers on private networks are rejected in the cloud but allowed self-hosted', function () {
    actAs($this->admin, $this->acme);

    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', imapMailboxInput($this->desk, [
        'settings' => ['imap' => ['host' => '127.0.0.1'], 'smtp' => ['host' => '10.0.0.5']],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings.imap.host', 'settings.smtp.host']);

    config(['harbor.multitenant' => false]);
    $mailbox = $this->acme->execute(fn () => app(CreateMailbox::class)->handle($this->admin, imapMailboxInput($this->desk, [
        'settings' => ['imap' => ['host' => '127.0.0.1'], 'smtp' => ['host' => '10.0.0.5']],
    ])));

    expect($mailbox->settings['imap']['host'])->toBe('127.0.0.1');
});

test('another workspace mailboxes are invisible and untouched', function () {
    $foreign = $this->other->execute(fn () => Mailbox::factory()->create(['name' => 'Other Support']));
    actAs($this->admin, $this->acme);

    $this->getJson('http://acme.harbor.test/api/v1/mailboxes')->assertJsonMissing(['id' => $foreign->id]);
    $this->getJson("http://acme.harbor.test/api/v1/mailboxes/{$foreign->id}")->assertNotFound();
    $this->patchJson("http://acme.harbor.test/api/v1/mailboxes/{$foreign->id}", ['name' => 'Hijacked'])->assertNotFound();
    $this->deleteJson("http://acme.harbor.test/api/v1/mailboxes/{$foreign->id}")->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Other Support');
});

test('admins test a mailbox: a test email to themselves and a look at the inbox, with errors reported', function () {
    Mail::fake();
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create());
    $url = "http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}/test";
    actAs($this->admin, $this->acme);

    mockImapClient(uidValidity: 7, uidNext: 101, messages: 3);
    $this->postJson($url)->assertOk()->assertExactJson([
        'sending' => ['passed' => true, 'message' => "Sent a test email to {$this->admin->email}."],
        'receiving' => ['passed' => true, 'message' => 'Connected. The inbox has 3 messages.'],
    ]);
    Mail::assertSent(MailboxTestMail::class, fn (MailboxTestMail $mail): bool => $mail->hasTo($this->admin->email) && $mail->hasFrom($mailbox->address));
    expect((new MailboxTestMail($mailbox))->render())->toContain('apple-touch-icon.png', 'Your mailbox can send mail', e($mailbox->name));

    $client = Mockery::mock(ImapMailboxClient::class);
    $client->shouldReceive('status')->andThrow(new RuntimeException('Authentication failed.'));
    $client->shouldReceive('disconnect')->once();
    app()->bind(ImapMailboxClient::class, fn () => $client);
    $this->postJson($url)->assertOk()->assertJsonPath('receiving', ['passed' => false, 'message' => 'Authentication failed.']);

    // A send-only mailbox isn't checked for receiving.
    $mailbox->update(['settings' => ['smtp' => $mailbox->settings['smtp']]]);
    $this->postJson($url)->assertOk()->assertJsonPath('receiving', null)->assertJsonPath('sending.passed', true);
});

test("agents can't test a mailbox, nor can anyone test another workspace's", function () {
    Mail::fake();
    $mailbox = $this->acme->execute(fn () => Mailbox::factory()->create());
    $foreign = $this->other->execute(fn () => Mailbox::factory()->create());

    actAs($this->agent, $this->acme);
    $this->postJson("http://acme.harbor.test/api/v1/mailboxes/{$mailbox->id}/test")->assertForbidden();

    actAs($this->admin, $this->acme);
    $this->postJson("http://acme.harbor.test/api/v1/mailboxes/{$foreign->id}/test")->assertNotFound();

    Mail::assertNothingSent();
});
