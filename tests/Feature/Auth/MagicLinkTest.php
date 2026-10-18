<?php

use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Request a magic link for the user and return the URL it emailed.
 */
function requestMagicLink(User $user): string
{
    Notification::fake();

    test()->post(route('magic-link.store'), ['email' => $user->email]);

    $url = null;
    Notification::assertSentTo($user, MagicLinkNotification::class, function (MagicLinkNotification $notification) use (&$url): bool {
        $url = $notification->url;

        return true;
    });

    return $url;
}

test('a known email is sent a link that signs the user in', function () {
    $user = User::factory()->create();

    $url = requestMagicLink($user);
    $response = $this->get($url);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('an unknown email gets the same response and no email', function () {
    Notification::fake();

    $response = $this->from(route('login'))->post(route('magic-link.store'), ['email' => 'nobody@example.com']);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'If that address has an account, a sign-in link is on its way.');
    Notification::assertNothingSent();
});

test('the email is required', function () {
    $response = $this->post(route('magic-link.store'));

    $response->assertSessionHasErrors(['email' => 'The email field is required.']);
});

test('a link cannot be used twice', function () {
    $user = User::factory()->create();
    $url = requestMagicLink($user);

    $this->get($url);
    auth()->logout();
    $response = $this->get($url);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['email' => 'This sign-in link is invalid or has expired.']);
});

test('an expired link is rejected', function () {
    $user = User::factory()->create();
    $url = requestMagicLink($user);

    $this->travel(16)->minutes();
    $response = $this->get($url);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('a tampered link is rejected', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $url = requestMagicLink($user);

    $response = $this->get(str_replace($user->id, $otherUser->id, $url));

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('a link issued on one workspace host is rejected on another', function () {
    $user = User::factory()->create();
    $url = requestMagicLink($user);

    $host = parse_url($url, PHP_URL_HOST);
    $response = $this->get(str_replace("://{$host}", "://other.{$host}", $url));

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('signing in with a link verifies an unverified email', function () {
    $user = User::factory()->unverified()->create();
    $url = requestMagicLink($user);

    $this->get($url);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
