<?php

use App\Enums\MailboxDriver;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('a new workspace gets a hosted mailbox on the inbound domain', function () {
    config(['harbor.multitenant' => true, 'harbor.mail.inbound_domain' => 'in.harbor.test']);

    $workspace = Workspace::factory()->create(['name' => 'Acme', 'subdomain' => 'acme'])->makeCurrent();

    $mailbox = Mailbox::sole();
    expect($mailbox->driver)->toBe(MailboxDriver::HOSTED)
        ->and($mailbox->name)->toBe('Acme')
        ->and($mailbox->address)->toBe('acme@in.harbor.test')
        ->and($mailbox->inbound_address)->toBe('acme@in.harbor.test')
        ->and($mailbox->workspace_id)->toBe($workspace->id)
        ->and($mailbox->defaultDesk->is(Desk::sole()))->toBeTrue();
});

test('a self-hosted workspace gets support@ on the inbound domain', function () {
    config(['harbor.multitenant' => false, 'harbor.mail.inbound_domain' => 'in.example.com']);

    Workspace::factory()->create()->makeCurrent();

    expect(Mailbox::sole()->address)->toBe('support@in.example.com');
});

test('no hosted mailbox is created without an inbound domain', function () {
    config(['harbor.mail.inbound_domain' => null]);

    Workspace::factory()->create()->makeCurrent();

    expect(Mailbox::count())->toBe(0);
});

test('a workspace can have only one hosted mailbox', function () {
    config(['harbor.mail.inbound_domain' => 'in.harbor.test']);

    Workspace::factory()->create()->makeCurrent();

    Mailbox::factory()->create(['driver' => MailboxDriver::HOSTED, 'settings' => null]);
})->throws(UniqueConstraintViolationException::class);

test('mailbox settings are encrypted at rest', function () {
    Workspace::factory()->create()->makeCurrent();

    $mailbox = Mailbox::factory()->create();

    $raw = DB::table('mailboxes')->where('id', $mailbox->id)->value('settings');
    expect($raw)->not->toContain('secret')
        ->and($mailbox->fresh()->settings['imap']['password'])->toBe('secret');
});
