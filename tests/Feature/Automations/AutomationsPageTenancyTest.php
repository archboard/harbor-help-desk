<?php

use App\Enums\AutomationRunStatus;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\BusinessSchedule;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Tag;
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

test('another workspace\'s automation can not be opened or updated in the builder', function () {
    $this->actingAs($this->admin);

    $this->get("{$this->web}/automations/{$this->automationB->id}/edit")->assertNotFound();
    $this->put("{$this->web}/automations/{$this->automationB->id}", ['name' => 'Hijacked'])->assertNotFound();

    $this->other->execute(fn () => expect($this->automationB->fresh()->name)->toBe('B secret automation'));
});

test('the builder offers only its own workspace\'s desks and catalog', function () {
    $this->other->execute(function () {
        CustomField::factory()->create(['name' => 'B secret field']);
        Tag::factory()->create(['name' => 'b-secret-tag']);
    });
    $this->workspace->execute(fn () => CustomField::factory()->create(['name' => 'Plan']));
    $this->actingAs($this->admin);

    foreach (['create', "{$this->own->id}/edit"] as $path) {
        $catalog = null;

        $this->get("{$this->web}/automations/$path")->assertOk()->assertInertia(function (Assert $page) use (&$catalog) {
            $page->has('desks', 1)->loadDeferredProps(function (Assert $reloaded) use (&$catalog) {
                $catalog = json_encode($reloaded->toArray()['props']['catalog']);
            });
        });

        expect($catalog)->toContain('Plan')->not->toContain('B Desk', 'B secret field', 'b-secret-tag');
    }
});

test('the builder offers only its own business schedules, and a relative date can not name another workspace\'s', function () {
    $own = BusinessSchedule::factory()->create(['name' => 'A hours']);
    $scheduleB = $this->other->execute(fn () => BusinessSchedule::factory()->create(['name' => 'B secret hours']));
    $this->actingAs($this->admin);

    $this->get("{$this->web}/automations/create")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('schedules', 1)
        ->where('schedules.0.id', $own->id));

    $rule = fn (string $scheduleId) => ['id' => 'c1', 'type' => 'condition', 'match' => 'all', 'rules' => [
        ['type' => 'rule', 'field' => 'ticket.due_at', 'operator' => 'lt', 'value' => ['relative' => ['amount' => -1, 'unit' => 'days', 'business_schedule_id' => $scheduleId]]],
        ['type' => 'rule', 'field' => 'time.since_created', 'operator' => 'gt', 'value' => ['amount' => 1, 'unit' => 'days', 'business_schedule_id' => $scheduleId]],
    ]];
    $save = fn (string $scheduleId) => $this->post("{$this->web}/automations", ['name' => 'Leak', 'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => [$rule($scheduleId)]]]);

    $save($scheduleB->id)->assertSessionHasErrors([
        'definition.steps.c1.rules.0.value.relative.business_schedule_id',
        'definition.steps.c1.rules.1.value.business_schedule_id',
    ]);
    expect(Automation::query()->where('name', 'Leak')->exists())->toBeFalse();

    $save($own->id)->assertSessionHasNoErrors();
});

test('an automation can not be stored on another workspace\'s desk', function () {
    $this->actingAs($this->admin)
        ->post("{$this->web}/automations", ['name' => 'Leak', 'desk_id' => $this->deskB->id, 'definition' => ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => []]])
        ->assertSessionHasErrors('desk_id');

    expect(Automation::query()->where('name', 'Leak')->exists())->toBeFalse();
    $this->other->execute(fn () => expect(Automation::query()->where('name', 'Leak')->exists())->toBeFalse());
});
