<?php

use App\Enums\PortalAccess;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * The URL of the sign-in link emailed to the address.
 */
function sentPortalLink(string $email): ?string
{
    $url = null;

    Notification::assertSentTo(
        Contact::query()->whereRaw('lower(email) = lower(?)', [$email])->first() ?? new Contact(['email' => $email]),
        MagicLinkNotification::class,
        function (MagicLinkNotification $notification) use (&$url): bool {
            $url = $notification->url;

            return true;
        },
    );

    return $url;
}

test('the login page renders', function () {
    $this->get(route('portal.login'))->assertInertia(fn (Assert $page) => $page->component('portal/Login'));
});

test('a known contact is sent a link that signs them in', function () {
    $contact = Contact::factory()->create();
    Notification::fake();

    $this->post(route('portal.login.store'), ['email' => $contact->email])
        ->assertSessionHas('status', 'If that address can sign in, a sign-in link is on its way.');

    $this->get(sentPortalLink($contact->email))->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($contact, 'contact');
    $this->get(route('portal.home'))->assertOk();
});

test('a link cannot be used twice', function () {
    $contact = Contact::factory()->create();
    Notification::fake();
    $this->post(route('portal.login.store'), ['email' => $contact->email]);
    $url = sentPortalLink($contact->email);

    $this->get($url);
    auth('contact')->logout();
    $response = $this->get($url);

    $this->assertGuest('contact');
    $response->assertRedirect(route('portal.login'));
    $response->assertSessionHasErrors(['email' => 'This sign-in link is invalid or has expired.']);
});

test('an expired link is rejected', function () {
    $contact = Contact::factory()->create();
    Notification::fake();
    $this->post(route('portal.login.store'), ['email' => $contact->email]);
    $url = sentPortalLink($contact->email);

    $this->travel(16)->minutes();
    $response = $this->get($url);

    $this->assertGuest('contact');
    $response->assertSessionHasErrors('email');
});

test('a tampered link is rejected', function () {
    $contact = Contact::factory()->create();
    Notification::fake();
    $this->post(route('portal.login.store'), ['email' => $contact->email]);
    $url = sentPortalLink($contact->email);

    $this->get(str_replace(urlencode($contact->email), urlencode('other@example.com'), $url))->assertSessionHasErrors('email');

    $this->assertGuest('contact');
});

test('under contacts access an unknown email gets the same response and nothing is sent', function () {
    $this->workspace->update(['settings' => ['portal_access' => PortalAccess::CONTACTS->value]]);
    Notification::fake();

    $this->post(route('portal.login.store'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', 'If that address can sign in, a sign-in link is on its way.');

    Notification::assertNothingSent();
    expect(Contact::count())->toBe(0);
});

test('under contacts access a link for an address that is no longer a contact creates nothing', function () {
    $contact = Contact::factory()->create();
    Notification::fake();
    $this->post(route('portal.login.store'), ['email' => $contact->email]);
    $url = sentPortalLink($contact->email);

    $this->workspace->update(['settings' => ['portal_access' => PortalAccess::CONTACTS->value]]);
    $contact->delete();
    $this->get($url)->assertSessionHasErrors('email');

    $this->assertGuest('contact');
    expect(Contact::count())->toBe(0);
});

test('under anyone access the contact is created on first sign-in', function () {
    Notification::fake();

    $this->post(route('portal.login.store'), ['email' => 'jane.doe@example.com']);
    $this->get(sentPortalLink('jane.doe@example.com'));

    $contact = Contact::sole();
    expect($contact->email)->toBe('jane.doe@example.com')->and($contact->name)->toBe('jane.doe');
    $this->assertAuthenticatedAs($contact, 'contact');
});

test('the email must be valid', function () {
    $this->post(route('portal.login.store'), ['email' => 'nope'])->assertSessionHasErrors('email');
});

test('everything under the portal is a 404 while it is off', function () {
    $this->workspace->update(['settings' => ['portal_enabled' => false]]);

    $this->get(route('portal.login'))->assertNotFound();
    $this->post(route('portal.login.store'), ['email' => 'a@example.com'])->assertNotFound();
    $this->get(route('portal.home'))->assertNotFound();
});

test('the portal home sends guests to the portal login', function () {
    $this->get(route('portal.home'))->assertRedirect(route('portal.login'));
});

test('the agent login page links to the portal only while it is enabled', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('portalEnabled', true));

    $this->workspace->update(['settings' => ['portal_enabled' => false]]);

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('portalEnabled', false));
});

test('signing out as a contact keeps an agent signed in', function () {
    $agent = User::factory()->create();
    $contact = Contact::factory()->create();

    $this->actingAs($agent)->actingAs($contact, 'contact');
    $this->post(route('portal.logout'))->assertRedirect(route('portal.login'));

    $this->assertGuest('contact');
    $this->assertAuthenticatedAs($agent, 'web');
});

test('a link signed on one workspace cannot sign in on another, and a contact session does not carry over', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    URL::forceRootUrl(null);
    $acme = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $contact = Contact::factory()->create(['email' => 'jane@example.com']);
    Notification::fake();

    $this->post('http://acme.harbor.test/portal/login', ['email' => $contact->email]);
    $url = sentPortalLink($contact->email);

    $foreignUrl = str_replace('://acme.', '://other.', $url);

    $this->flushSession();
    $this->get($foreignUrl)->assertSessionHasErrors('email');
    $this->assertGuest('contact');
    expect($other->execute(fn () => Contact::count()))->toBe(0);

    $this->flushSession();
    $this->get($url)->assertRedirect();
    $this->assertAuthenticatedAs($contact, 'contact');

    // The other workspace never sees the acme contact.
    auth('contact')->forgetUser();
    $this->get('http://other.harbor.test/portal')->assertRedirect('http://other.harbor.test/portal/login');
    expect($acme->id)->not->toBe($other->id);
});
