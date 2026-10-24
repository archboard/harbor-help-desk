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
                'away_at' => null,
                'settings' => ['mail_notification_mailbox_id' => null, 'mail_new_tickets_per_sender_per_hour' => 20, 'attachments_max_size_kb' => 25600, 'attachments_blocked_extensions' => ['bat', 'cmd', 'com', 'cpl', 'dll', 'exe', 'hta', 'jar', 'js', 'jse', 'lnk', 'msi', 'msp', 'ps1', 'scr', 'sh', 'vbe', 'vbs', 'wsf'], 'automations_allow_private_urls' => false, 'portal_enabled' => true, 'portal_access' => 'anyone', 'surveys_enabled' => false],
            ]));
});
