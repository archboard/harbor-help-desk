<?php

use App\Enums\MailboxDriver;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->desk = Desk::sole();
});

test('admins see the mailboxes, desks and configured providers, never secrets', function () {
    config(['services.google_mail.client_id' => 'google-client', 'services.microsoft_mail.client_id' => null]);
    Mailbox::factory()->create(['default_desk_id' => $this->desk->id, 'polled_at' => now()]);
    Mailbox::factory()->create([
        'driver' => MailboxDriver::GOOGLE,
        'default_desk_id' => $this->desk->id,
        'settings' => ['access_token' => 'access-secret', 'refresh_token' => 'refresh-secret', 'expires_at' => 0],
        'push_expires_at' => now()->addDay(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('workspace.email.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('workspace/Email')
        ->has('mailboxes', 2)
        ->where('mailboxes.0.default_desk_id', $this->desk->id)
        ->where('mailboxes.0.receives_by', 'poll')
        ->where('mailboxes.0.settings.imap.host', 'imap.example.com')
        ->missing('mailboxes.0.settings.imap.password')
        ->where('mailboxes.1.receives_by', 'push')
        ->missing('mailboxes.1.settings')
        ->where('desks.0.id', $this->desk->id)
        ->where('oauthProviders', ['google' => true, 'microsoft' => false]));

    expect($response->getContent())->not->toContain('secret');
});

test('admins add, update and delete an IMAP mailbox through the web routes', function () {
    $billing = Desk::factory()->create(['name' => 'Billing']);
    $server = ['host' => '1.1.1.1', 'port' => 993, 'encryption' => 'ssl', 'username' => 'help@acme.com', 'password' => 'hunter2'];
    $this->actingAs($this->admin);

    $this->post(route('workspace.email.store'), [
        'name' => 'Acme Support',
        'address' => 'help@acme.com',
        'default_desk_id' => $this->desk->id,
        'settings' => ['imap' => $server, 'smtp' => [...$server, 'port' => 587, 'encryption' => 'starttls']],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $mailbox = Mailbox::sole();
    expect($mailbox->driver)->toBe(MailboxDriver::IMAP);

    $this->patch(route('workspace.email.update', $mailbox), [
        'name' => 'Acme Help',
        'signature' => '— Acme',
        'default_desk_id' => $this->desk->id,
        'routing' => [['address' => 'billing@acme.com', 'desk_id' => $billing->id]],
    ])->assertSessionHasNoErrors();
    expect($mailbox->fresh())
        ->name->toBe('Acme Help')
        ->signature->toBe('— Acme')
        ->routing->toBe([['address' => 'billing@acme.com', 'desk_id' => $billing->id]]);

    $this->delete(route('workspace.email.destroy', $mailbox))->assertRedirect();
    expect(Mailbox::count())->toBe(0);
});

test('a routing rule must point at a desk in this workspace', function () {
    $mailbox = Mailbox::factory()->create();
    $foreignDesk = Workspace::factory()->create()->execute(fn () => Desk::sole());
    $this->workspace->makeCurrent();

    $this->actingAs($this->admin)
        ->patch(route('workspace.email.update', $mailbox), ['routing' => [['address' => 'billing@acme.com', 'desk_id' => $foreignDesk->id]]])
        ->assertSessionHasErrors('routing.0.desk_id');
});

test('agents cannot manage mailboxes', function () {
    $mailbox = Mailbox::factory()->create(['name' => 'Support']);
    $this->actingAs($this->agent);

    $this->get(route('workspace.email.index'))->assertForbidden();
    $this->post(route('workspace.email.store'), ['name' => 'New'])->assertForbidden();
    $this->patch(route('workspace.email.update', $mailbox), ['name' => 'Renamed'])->assertForbidden();
    $this->delete(route('workspace.email.destroy', $mailbox))->assertForbidden();

    expect($mailbox->fresh()->name)->toBe('Support');
});

test("another workspace's mailboxes are not reachable", function () {
    $foreign = Workspace::factory()->create()->execute(fn () => Mailbox::factory()->create(['name' => 'Foreign']));
    $this->workspace->makeCurrent();
    $this->actingAs($this->admin);

    $this->get(route('workspace.email.index'))->assertInertia(fn (Assert $page) => $page->has('mailboxes', 0));
    $this->patch(route('workspace.email.update', $foreign->id), ['name' => 'Hijacked'])->assertNotFound();
    $this->delete(route('workspace.email.destroy', $foreign->id))->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Foreign');
});
