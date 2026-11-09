<?php

use App\Models\User;
use App\Support\Locales;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    // A fake `xx` locale, in a temporary lang path so it never ships as an available locale.
    $this->langPath = sys_get_temp_dir().'/harbor-lang-'.uniqid();
    File::ensureDirectoryExists($this->langPath);
    File::put($this->langPath.'/xx.json', json_encode(['Profile updated.' => 'xx: Profile updated.']));

    app()->useLangPath($this->langPath);
    app('translator')->getLoader()->addJsonPath($this->langPath);
});

afterEach(function () {
    File::deleteDirectory($this->langPath);
});

test('the available locales are English plus the lang json files', function () {
    expect(Locales::available())->toBe(['en', 'xx']);
});

test('the user\'s locale wins, then Accept-Language, then the default', function () {
    $user = User::factory()->create(['locale' => 'xx']);

    $this->actingAs($user)
        ->get(route('profile.edit'), ['Accept-Language' => 'en'])
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'xx'));

    $user->update(['locale' => null]);

    $this->actingAs($user)
        ->get(route('profile.edit'), ['Accept-Language' => 'fr;q=0.9, xx;q=0.8'])
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'xx'));

    app()->setLocale('en');

    $this->actingAs($user)
        ->get(route('profile.edit'), ['Accept-Language' => 'fr'])
        ->assertInertia(fn (Assert $page) => $page->where('locale', config('app.locale')));
});

test('a saved locale that is no longer available falls back to the default', function () {
    $user = User::factory()->create(['locale' => 'zz']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', config('app.locale')));
});

test('strings are looked up in the locale\'s json file', function () {
    $user = User::factory()->create(['locale' => 'xx']);

    $this->actingAs($user)->get(route('profile.edit'));

    expect(__('Profile updated.'))->toBe('xx: Profile updated.')
        ->and(__('Unknown string'))->toBe('Unknown string');
});

test('mail to a user follows their locale', function () {
    Mail::fake();
    $user = User::factory()->create(['locale' => 'xx']);

    Mail::to($user)->send(new class extends Mailable {});

    Mail::assertSent(Mailable::class, fn (Mailable $mail): bool => $mail->locale === 'xx');
});

test('the profile accepts only available locales, and null for the browser default', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $input = ['name' => $user->name, 'email' => $user->email];

    $this->patch(route('profile.update'), [...$input, 'locale' => 'zz'])->assertSessionHasErrors('locale');

    $this->patch(route('profile.update'), [...$input, 'locale' => 'xx'])->assertSessionHasNoErrors();
    expect($user->fresh()->locale)->toBe('xx');

    $this->patch(route('profile.update'), [...$input, 'locale' => null])->assertSessionHasNoErrors();
    expect($user->fresh()->locale)->toBeNull();
});
