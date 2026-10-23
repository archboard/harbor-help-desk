<?php

use App\Enums\AutomationRunStatus;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->own = Automation::factory()->create(['name' => 'A automation', 'position' => 1]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->other->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    [$this->automationB, $this->deskB] = $this->other->execute(function () {
        $automation = Automation::factory()->create(['name' => 'B secret automation', 'position' => 1]);
        AutomationRun::query()->create([
            'workspace_id' => $this->other->id, 'automation_id' => $automation->id, 'depth' => 0,
            'status' => AutomationRunStatus::COMPLETED, 'definition' => $automation->definition, 'log' => [], 'state' => [],
        ]);

        return [$automation, Desk::factory()->create(['name' => 'B Desk'])];
    });

    $this->web = 'http://acme.harbor.test';
});

test('the page lists only its own workspace\'s automations, desks and run counts', function () {
    $this->actingAs($this->admin)->get("{$this->web}/automations")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('automations', 1)
        ->where('automations.0.id', $this->own->id)
        ->where('automations.0.runs_last24h', 0)
        ->where('automations.0.last_run', null));

    expect($this->actingAs($this->admin)->get("{$this->web}/automations")->getContent())->not->toContain('B secret automation', 'B Desk');
});

test('another workspace\'s automation can not be toggled, reordered, duplicated or deleted', function () {
    $this->actingAs($this->admin);

    $this->put("{$this->web}/automations/{$this->automationB->id}/enabled", ['enabled' => true])->assertNotFound();
    $this->post("{$this->web}/automations/{$this->automationB->id}/duplicate")->assertNotFound();
    $this->delete("{$this->web}/automations/{$this->automationB->id}")->assertNotFound();
    $this->post("{$this->web}/automations/reorder", ['ids' => [$this->own->id, $this->automationB->id]])->assertSessionHasErrors('ids.1');

    $this->other->execute(function () {
        expect(Automation::query()->count())->toBe(1)
            ->and($this->automationB->fresh()->enabled)->toBeFalse()
            ->and($this->automationB->fresh()->position)->toBe(1)
            ->and(AutomationRun::query()->count())->toBe(1);
    });
    expect(Automation::query()->count())->toBe(1);
});

test('a duplicate lands in the current workspace', function () {
    $this->actingAs($this->admin)->post("{$this->web}/automations/{$this->own->id}/duplicate")->assertRedirect();

    expect(Automation::query()->count())->toBe(2)
        ->and(Automation::query()->where('name', 'A automation (copy)')->sole()->workspace_id)->toBe($this->workspace->id);
    $this->other->execute(fn () => expect(Automation::query()->count())->toBe(1));
});
