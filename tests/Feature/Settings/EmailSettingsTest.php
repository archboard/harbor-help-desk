<?php

use App\Enums\MailboxDriver;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;
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
    config(['services.google_mail.client_id' => 'google-client']);
    $this->workspace->update(['settings' => ['microsoft_client_id' => '00000000-0000-0000-0000-000000000001']]);
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
        ->where('oauthProviders', ['google' => true, 'microsoft' => false])
        ->where('microsoftHasSecret', false));

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

test('a mailbox may be send-only, with an SMTP server that needs no login, but not without either server', function () {
    $mailbox = ['name' => 'Acme Support', 'address' => 'help@acme.com', 'default_desk_id' => $this->desk->id];
    $this->actingAs($this->admin);

    $this->post(route('workspace.email.store'), [...$mailbox, 'settings' => ['imap' => null, 'smtp' => null]])
        ->assertSessionHasErrors(['settings.imap', 'settings.smtp']);

    $this->post(route('workspace.email.store'), [...$mailbox, 'settings' => ['imap' => ['host' => '1.1.1.1'], 'smtp' => null]])
        ->assertSessionHasErrors(['settings.imap.port', 'settings.imap.username', 'settings.imap.password'])
        ->assertSessionDoesntHaveErrors(['settings.smtp']);

    $this->post(route('workspace.email.store'), [...$mailbox, 'settings' => ['imap' => null, 'smtp' => ['host' => '1.1.1.1', 'port' => 25, 'encryption' => 'starttls', 'username' => '', 'password' => '']]])
        ->assertSessionHasNoErrors();

    expect(Mailbox::sole())->receivesMail()->toBeFalse()->sendsMail()->toBeTrue();
    $this->get(route('workspace.email.index'))->assertInertia(fn (Assert $page) => $page
        ->where('mailboxes.0.receives_by', 'never')
        ->where('mailboxes.0.settings.imap', null)
        ->where('mailboxes.0.settings.smtp.username', null));
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

test('testing a mailbox from the settings page reports the result as a toast', function () {
    Mail::fake();
    mockImapClient(uidValidity: 7, uidNext: 101, messages: 1);
    $mailbox = Mailbox::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('workspace.email.test', $mailbox))
        ->assertRedirect()
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => "Sending: Sent a test email to {$this->admin->email}. Receiving: Connected. The inbox has 1 message."]);
});

test('admins save the workspace Microsoft app, and its secret is encrypted and never shown', function () {
    $app = ['microsoft_client_id' => '6f0c1d2e-3b4a-4c5d-8e9f-0a1b2c3d4e5f', 'microsoft_tenant' => 'acme.onmicrosoft.com'];
    $this->actingAs($this->admin);

    $this->patch(route('workspace.settings.update'), [...$app, 'microsoft_client_secret' => 'entra-secret'])->assertSessionHasNoErrors();

    $workspace = $this->workspace->fresh();
    expect($workspace->microsoft_client_secret)->toBe('entra-secret')
        ->and($workspace->getRawOriginal('microsoft_client_secret'))->not->toContain('entra-secret')
        ->and($workspace->getRawOriginal('settings'))->not->toContain('entra-secret');

    // Leaving the secret out keeps it.
    $this->patch(route('workspace.settings.update'), $app)->assertSessionHasNoErrors();
    expect($this->workspace->fresh()->microsoft_client_secret)->toBe('entra-secret');

    $response = $this->get(route('workspace.email.index'))->assertInertia(fn (Assert $page) => $page
        ->where('oauthProviders.microsoft', true)
        ->where('microsoftHasSecret', true)
        ->where('workspace.settings.microsoft_client_id', $app['microsoft_client_id'])
        ->where('workspace.settings.microsoft_tenant', 'acme.onmicrosoft.com'));
    expect($response->getContent())->not->toContain('entra-secret');

    $this->patch(route('workspace.settings.update'), ['microsoft_client_secret' => null])->assertSessionHasNoErrors();
    expect($this->workspace->fresh()->microsoft_client_secret)->toBeNull();
});

test('the Microsoft app ID must be a GUID and the tenant a plain name', function () {
    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['microsoft_client_id' => 'nope', 'microsoft_tenant' => 'evil.com/x?'])
        ->assertSessionHasErrors(['microsoft_client_id', 'microsoft_tenant']);
});

test('agents cannot set the Microsoft app', function () {
    $this->actingAs($this->agent)
        ->patch(route('workspace.settings.update'), ['microsoft_client_secret' => 'stolen'])
        ->assertForbidden();

    expect($this->workspace->fresh()->microsoft_client_secret)->toBeNull();
});
