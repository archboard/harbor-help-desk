<?php

use App\Actions\Mailboxes\CreateMailbox;
use App\Enums\MailboxDriver;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;

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

    $id = $this->postJson('http://acme.harbor.test/api/v1/mailboxes', imapMailboxInput($this->desk, [
        'routing' => [['address' => 'billing@acme.com', 'desk_id' => $billing->id]],
    ]))
        ->assertCreated()
        ->assertJsonPath('driver', 'imap')
        ->assertJsonPath('settings.imap.host', '1.1.1.1')
        ->assertJsonPath('routing.0.desk_id', $billing->id)
        ->assertJsonMissingPath('settings.imap.password')
        ->assertJsonMissingPath('settings.smtp.password')
        ->json('id');

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

test('mailboxes are validated', function () {
    $archived = $this->acme->execute(fn () => Desk::factory()->create(['archived_at' => now()]));
    $foreignDesk = $this->other->execute(fn () => Desk::sole());
    actAs($this->admin, $this->acme);

    $this->postJson('http://acme.harbor.test/api/v1/mailboxes', ['driver' => 'imap'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'address', 'default_desk_id', 'settings.imap.host', 'settings.imap.password', 'settings.smtp.port']);

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
