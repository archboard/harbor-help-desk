<?php

use App\Actions\Install\CheckServices;
use App\Data\Install\ServiceCheckData;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, string>
 */
function ownerInput(array $overrides = []): array
{
    return [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
        ...$overrides,
    ];
}

describe('self-hosted', function () {
    beforeEach(fn () => config(['harbor.multitenant' => false]));

    test('a fresh install redirects to the install page', function () {
        $this->get('/tickets')->assertRedirect(route('install'));

        $this->get(route('install'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('install/Install'));
    });

    test('the install page checks the services', function () {
        config(['reverb.servers.reverb.host' => '127.0.0.1', 'reverb.servers.reverb.port' => 1]);

        $this->get(route('install'))->assertInertia(fn (Assert $page) => $page->has('checks', 5));

        $checks = collect(app(CheckServices::class)->handle())->keyBy('name');
        expect($checks['Redis'])->toBeInstanceOf(ServiceCheckData::class)->ok->toBeTrue()
            ->and($checks['Postgres']->ok)->toBeTrue()
            ->and($checks['Reverb'])->ok->toBeFalse()->detail->toContain('127.0.0.1:1');
    });

    test('installing creates the workspace and a verified owner, signed in', function () {
        $this->post(route('install.store'), ownerInput(['workspace_name' => 'Acme']))
            ->assertRedirect(route('tickets.index'));

        $workspace = Workspace::sole();
        $user = User::sole();
        expect($workspace)->name->toBe('Acme')->subdomain->toBe('default')
            ->and($user->hasVerifiedEmail())->toBeTrue()
            ->and($workspace->users()->sole()->pivot->role)->toBe(WorkspaceRole::OWNER);
        $this->assertAuthenticatedAs($user);
    });

    test('invalid input creates nothing and reports the workspace name under its own key', function () {
        $this->post(route('install.store'), ownerInput(['workspace_name' => '', 'password_confirmation' => 'other']))
            ->assertSessionHasErrors('workspace_name');

        $this->post(route('install.store'), ownerInput(['workspace_name' => 'Acme', 'password_confirmation' => 'other']))
            ->assertSessionHasErrors('password');

        expect(Workspace::count())->toBe(0)->and(User::count())->toBe(0);
    });

    test('the install page is gone once a workspace exists', function () {
        Workspace::factory()->create();

        $this->get(route('install'))->assertNotFound();
        $this->post(route('install.store'), ownerInput(['workspace_name' => 'Other']))->assertNotFound();
    });
});

describe('cloud', function () {
    beforeEach(function () {
        config(['harbor.multitenant' => true]);
        $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    });

    test('the setup link must be signed and unexpired', function () {
        $this->get($this->acme->url('setup?workspace='.$this->acme->id))->assertForbidden();

        $link = $this->acme->setupUrl();
        $this->travel(15)->days();

        $this->get($link)->assertForbidden();
    });

    test('the setup link opens the install page, which claims the workspace', function () {
        $this->get($this->acme->setupUrl())->assertRedirect($this->acme->url('install'));
        $this->get($this->acme->url('install'))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('checks'));

        $this->post($this->acme->url('install'), ownerInput())->assertRedirect($this->acme->url('tickets'));

        expect($this->acme->users()->sole())
            ->email->toBe('jane@example.com')
            ->pivot->role->toBe(WorkspaceRole::OWNER)
            ->and(Workspace::count())->toBe(1);
    });

    test('the install page needs the setup link', function () {
        $this->get($this->acme->url('install'))->assertNotFound();
        $this->post($this->acme->url('install'), ownerInput())->assertNotFound();
    });

    test('the link and the page are gone once the workspace is claimed', function () {
        $link = $this->acme->setupUrl();
        $this->get($link);
        $this->acme->users()->attach(User::factory()->create(), ['role' => WorkspaceRole::OWNER]);

        $this->get($this->acme->url('install'))->assertNotFound();
        $this->post($this->acme->url('install'), ownerInput())->assertNotFound();
        $this->get($link)->assertNotFound();
        expect($this->acme->users()->count())->toBe(1);
    });

    test('a setup session for one workspace cannot install on another', function () {
        $other = Workspace::factory()->create(['subdomain' => 'other']);

        $this->withSession(['setup_workspace_id' => $this->acme->id])
            ->post($other->url('install'), ownerInput())
            ->assertNotFound();

        expect($other->hasMembers())->toBeFalse();
    });

    test('a setup link only works on its own workspace\'s host', function () {
        $other = Workspace::factory()->create(['subdomain' => 'other']);
        $link = Str::replace('//acme.', '//other.', $this->acme->setupUrl());

        $this->get($link)->assertNotFound();
        $this->get($other->url('install'))->assertNotFound();
    });
});
