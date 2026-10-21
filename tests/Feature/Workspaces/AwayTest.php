<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);
});

test('toggling away sets and clears the membership and is shared with pages', function () {
    $this->actingAs($this->agent)->put(route('availability.update'), ['away' => true])->assertRedirect();
    expect($this->agent->membership($this->workspace)->away_at)->not->toBeNull();

    $this->actingAs($this->agent)->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('workspace.away_at', null));

    $this->actingAs($this->agent)->put(route('availability.update'), ['away' => false])->assertRedirect();
    expect($this->agent->membership($this->workspace)->away_at)->toBeNull();
});
