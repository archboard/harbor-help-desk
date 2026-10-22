<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

test('a self-hosted admin toggles private urls and the page shows it', function () {
    config(['harbor.multitenant' => false]);

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), ['automations_allow_private_urls' => true])->assertSessionDoesntHaveErrors();

    expect($this->workspace->fresh()->settings->effectiveAllowPrivateUrls())->toBeTrue();

    $this->actingAs($this->admin)->get(route('workspace.automations.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('workspace/Automations')->where('workspace.settings.automations_allow_private_urls', true)->where('multitenant', false));

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), ['automations_allow_private_urls' => false]);
    expect($this->workspace->fresh()->settings->automationsAllowPrivateUrls)->toBeFalse();
});

test('the setting can not be enabled in multitenant mode, in the web or the api', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $this->workspace->update(['subdomain' => 'acme']);
    URL::forceRootUrl('http://acme.harbor.test');

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), ['automations_allow_private_urls' => true])
        ->assertSessionHasErrors('automations_allow_private_urls');

    actAs($this->admin, $this->workspace);
    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['automations_allow_private_urls' => true])
        ->assertUnprocessable()->assertJsonValidationErrors('automations_allow_private_urls');

    $this->actingAs($this->admin)->get(route('workspace.automations.edit'))->assertNotFound();
    expect($this->workspace->fresh()->settings->automationsAllowPrivateUrls)->toBeFalse();
});

test('a stored true is ignored in multitenant mode', function () {
    $this->workspace->update(['settings' => ['automations_allow_private_urls' => true]]);

    config(['harbor.multitenant' => false]);
    expect(Workspace::find($this->workspace->id)->settings->effectiveAllowPrivateUrls())->toBeTrue();

    config(['harbor.multitenant' => true]);
    expect(Workspace::find($this->workspace->id)->settings->effectiveAllowPrivateUrls())->toBeFalse();
});
