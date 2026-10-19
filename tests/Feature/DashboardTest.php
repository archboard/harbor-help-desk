<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('guests are redirected to the login page', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});

test('the old dashboard address redirects to the ticket list', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect('/tickets');
});

test('every page shares the signed-in user and the current workspace', function () {
    $user = User::factory()->create(['timezone' => 'Europe/Brussels']);
    $this->workspace->users()->attach($user, ['role' => WorkspaceRole::ADMIN]);

    $this->actingAs($user)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at->toIso8601String(),
                'timezone' => 'Europe/Brussels',
            ])
            ->where('workspace', [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'subdomain' => $this->workspace->subdomain,
                'is_admin' => true,
            ]));
});
