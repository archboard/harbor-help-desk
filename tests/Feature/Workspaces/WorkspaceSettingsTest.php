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
        ->get(route('workspace.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Workspace')
            ->where('workspace', [
                'id' => $this->workspace->id,
                'name' => $this->workspace->name,
                'subdomain' => $this->workspace->subdomain,
                'is_admin' => true,
            ]));

    $this->actingAs($this->admin)
        ->patch(route('workspace.update'), ['name' => 'Acme Support'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->name)->toBe('Acme Support');
});

test('the name is required', function () {
    $this->actingAs($this->admin)
        ->patch(route('workspace.update'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('agents cannot manage the workspace', function () {
    $this->actingAs($this->agent)
        ->get(route('desks.index'))
        ->assertInertia(fn (Assert $page) => $page->where('workspace.is_admin', false));

    $this->actingAs($this->agent)->get(route('workspace.edit'))->assertForbidden();
    $this->actingAs($this->agent)->patch(route('workspace.update'), ['name' => 'Renamed'])->assertForbidden();

    expect($this->workspace->fresh()->name)->not->toBe('Renamed');
});
