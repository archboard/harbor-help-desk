<?php

use App\Actions\Desks\UpdateDesk;
use App\Data\Workspaces\OnboardingData;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @return array<string, bool>
 */
function onboardingSteps(Workspace $workspace): array
{
    return collect(OnboardingData::forWorkspace($workspace)->steps)->pluck('complete', 'key')->all();
}

beforeEach(function () {
    config(['harbor.mail.inbound_domain' => null]);
    $this->workspace = Workspace::factory()->create()->makeCurrent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

test('a fresh workspace has nothing done', function () {
    $onboarding = OnboardingData::forWorkspace($this->workspace);

    expect(array_map(fn ($step) => $step->key, $onboarding->steps))->toBe(['email', 'team', 'desk', 'first_ticket'])
        ->and(onboardingSteps($this->workspace))->toBe(['email' => false, 'team' => false, 'desk' => false, 'first_ticket' => false])
        ->and($onboarding->complete)->toBeFalse()
        ->and($onboarding->inboundAddress)->toBeNull()
        ->and($onboarding->defaultDeskId)->toBe(Desk::query()->oldest()->value('id'));
});

test('a mailbox completes the email step', function () {
    Mailbox::factory()->create();

    expect(onboardingSteps($this->workspace)['email'])->toBeTrue();
});

test('a second member or a pending invitation completes the team step', function () {
    $this->workspace->users()->attach(User::factory()->create(), ['role' => WorkspaceRole::AGENT]);
    expect(onboardingSteps($this->workspace)['team'])->toBeTrue();

    $this->workspace->users()->detach($this->workspace->users()->where('users.id', '!=', $this->admin->id)->first());
    expect(onboardingSteps($this->workspace)['team'])->toBeFalse();

    WorkspaceInvitation::factory()->create();
    expect(onboardingSteps($this->workspace)['team'])->toBeTrue();
});

test('reviewing the default desk or adding another completes the desk step', function () {
    $desk = Desk::query()->sole();
    expect(onboardingSteps($this->workspace)['desk'])->toBeFalse();

    $this->travel(5)->minutes();
    app(UpdateDesk::class)->handle($this->admin, $desk, ['name' => 'Support']);
    expect(onboardingSteps($this->workspace)['desk'])->toBeTrue();

    $desk->forceFill(['updated_at' => $desk->created_at])->saveQuietly();
    expect(onboardingSteps($this->workspace)['desk'])->toBeFalse();

    Desk::factory()->create();
    expect(onboardingSteps($this->workspace)['desk'])->toBeTrue();
});

test('an archived desk does not count as another desk', function () {
    Desk::factory()->create()->delete();

    expect(onboardingSteps($this->workspace)['desk'])->toBeFalse();
});

test('a ticket, even a trashed one, completes the first ticket step', function () {
    Ticket::factory()->create()->delete();

    expect(onboardingSteps($this->workspace)['first_ticket'])->toBeTrue();
});

test('the checklist is complete when every step is', function () {
    Mailbox::factory()->create();
    WorkspaceInvitation::factory()->create();
    Desk::factory()->create();
    Ticket::factory()->create();

    expect(OnboardingData::forWorkspace($this->workspace)->complete)->toBeTrue();
});

test('the inbound address is the hosted mailbox address when an inbound domain is configured', function () {
    config(['harbor.mail.inbound_domain' => 'in.harbor.test']);
    $workspace = Workspace::factory()->create()->makeCurrent();

    expect(OnboardingData::forWorkspace($workspace)->inboundAddress)->toBe($workspace->subdomain.'@in.harbor.test');
});

test('dismissing is stored through the settings API', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);
    $admin = User::factory()->create();
    $workspace->users()->attach($admin, ['role' => WorkspaceRole::OWNER]);
    actAs($admin, $workspace);

    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['onboarding_dismissed' => true])
        ->assertOk()
        ->assertJsonPath('settings.onboarding_dismissed', true);

    expect($workspace->fresh()->settings->onboardingDismissed)->toBeTrue();
});

test('a step an admin marks done counts as done', function () {
    $this->workspace->update(['settings' => [...$this->workspace->settings->toArray(), 'onboarding_skipped_steps' => ['desk']]]);

    $desk = collect(OnboardingData::forWorkspace($this->workspace->fresh())->steps)->firstWhere('key', 'desk');

    expect($desk)->complete->toBeTrue()->skipped->toBeTrue()
        ->and(onboardingSteps($this->workspace->fresh()))->toBe(['email' => false, 'team' => false, 'desk' => true, 'first_ticket' => false]);
});

test('marking steps done is stored through the settings API, and only known steps are accepted', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);
    $admin = User::factory()->create();
    $workspace->users()->attach($admin, ['role' => WorkspaceRole::OWNER]);
    actAs($admin, $workspace);

    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['onboarding_skipped_steps' => ['desk', 'billing']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('onboarding_skipped_steps.1');

    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['onboarding_skipped_steps' => ['desk']])
        ->assertOk()
        ->assertJsonPath('settings.onboarding_skipped_steps', ['desk']);
});

describe('api', function () {
    beforeEach(function () {
        config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

        $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
        $this->other = Workspace::factory()->create(['subdomain' => 'other']);

        $this->owner = User::factory()->create();
        $this->acme->users()->attach($this->owner, ['role' => WorkspaceRole::OWNER]);
        $this->agent = User::factory()->create();
        $this->acme->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

        $this->url = 'http://acme.harbor.test/api/v1/workspace/onboarding';
    });

    test('an admin reads the checklist', function () {
        actAs($this->owner, $this->acme);

        $this->getJson($this->url)
            ->assertOk()
            ->assertJsonPath('steps.0', ['key' => 'email', 'complete' => false, 'skipped' => false])
            ->assertJsonPath('steps.1', ['key' => 'team', 'complete' => true, 'skipped' => false])
            ->assertJsonPath('complete', false)
            ->assertJsonStructure(['steps', 'inbound_address', 'default_desk_id', 'complete']);
    });

    test('an agent is forbidden', function () {
        actAs($this->agent, $this->acme);

        $this->getJson($this->url)->assertForbidden();
    });

    test('another workspace\'s state never completes a step', function () {
        $this->other->execute(function () {
            Mailbox::factory()->create();
            Desk::factory()->create();
            Ticket::factory()->create();
            WorkspaceInvitation::factory()->create();
        });
        $this->other->users()->attach(User::factory()->create(), ['role' => WorkspaceRole::AGENT]);
        $this->acme->users()->detach($this->agent);
        actAs($this->owner, $this->acme);

        $this->getJson($this->url)
            ->assertOk()
            ->assertJsonPath('steps.*.complete', [false, false, false, false])
            ->assertJsonPath('default_desk_id', $this->acme->execute(fn () => Desk::query()->value('id')));
    });

    test('a token for another workspace cannot read this one', function () {
        $user = User::factory()->create();
        $this->other->users()->attach($user, ['role' => WorkspaceRole::OWNER]);
        actAs($user, $this->other);

        $this->getJson($this->url)->assertForbidden();
    });
});
