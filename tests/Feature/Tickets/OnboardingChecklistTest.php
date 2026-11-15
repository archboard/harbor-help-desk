<?php

use App\Enums\WorkspaceRole;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    ['workspace' => $this->workspace, 'agent' => $this->agent] = acmeWithAgent();

    $this->owner = User::factory()->create();
    $this->workspace->users()->attach($this->owner, ['role' => WorkspaceRole::OWNER]);
});

test('an owner on a fresh workspace gets the checklist', function () {
    $this->actingAs($this->owner)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('onboarding.steps', 4)
            ->where('onboarding.complete', false));
});

test('agents get no checklist', function () {
    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
});

test('a dismissed checklist is not sent', function () {
    $this->workspace->settings->onboardingDismissed = true;
    $this->workspace->save();

    $this->actingAs($this->owner)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
});

test('a completed checklist loads as null', function () {
    $this->workspace->users()->attach(User::factory()->create(), ['role' => WorkspaceRole::AGENT]);
    $this->workspace->desks()->update(['updated_at' => now()->addMinute()]);
    Ticket::factory()->create();
    Mailbox::factory()->create();

    $this->actingAs($this->owner)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding', null));
});

test('another workspace\'s dismissal and progress do not affect this one', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    URL::forceRootUrl(rtrim($this->workspace->url(), '/'));

    $other = Workspace::factory()->create();
    $other->settings->onboardingDismissed = true;
    $other->save();
    $other->execute(fn () => Ticket::factory()->create());
    $this->workspace->makeCurrent();

    $this->actingAs($this->owner)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.steps.3.complete', false));
});
