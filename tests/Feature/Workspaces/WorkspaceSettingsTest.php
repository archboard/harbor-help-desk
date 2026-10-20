<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('admins see the workspace page and rename the workspace', function () {
    $this->actingAs($this->admin)
        ->get(route('workspace.general.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('workspace/General')
            ->where('workspace', [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'subdomain' => $this->workspace->subdomain,
                'is_admin' => true,
                'settings' => ['mail_notification_mailbox_id' => null, 'mail_new_tickets_per_sender_per_hour' => 20],
            ]));

    $this->actingAs($this->admin)
        ->patch(route('workspace.general.update'), ['name' => 'Acme Support'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->name)->toBe('Acme Support');
});

test('the name is required', function () {
    $this->actingAs($this->admin)
        ->patch(route('workspace.general.update'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('agents cannot manage the workspace', function () {
    $this->actingAs($this->agent)
        ->get(route('desks.index'))
        ->assertInertia(fn (Assert $page) => $page->where('workspace.is_admin', false));

    $this->actingAs($this->agent)->get(route('workspace.general.edit'))->assertForbidden();
    $this->actingAs($this->agent)->patch(route('workspace.general.update'), ['name' => 'Renamed'])->assertForbidden();

    expect($this->workspace->fresh()->name)->not->toBe('Renamed');
});

test('workspace pages are forbidden to non-admin agents and the index redirects to general', function () {
    foreach (['workspace.general.edit', 'workspace.email.index', 'workspace.custom-fields.index'] as $name) {
        $this->actingAs($this->agent)->get(route($name))->assertForbidden();
    }

    $this->actingAs($this->admin)->get('/workspace')->assertRedirect('/workspace/general');
});
