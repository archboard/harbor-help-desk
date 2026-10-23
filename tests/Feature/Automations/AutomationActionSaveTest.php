<?php

use App\Automations\DefinitionValidator;
use App\Automations\EditorScope;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\BusinessSchedule;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->otherDesk = Desk::factory()->create();
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->colleague = deskMember($this->workspace, $this->desk);
    $this->stranger = deskMember($this->workspace, $this->otherDesk);
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->base = 'http://acme.harbor.test/api/v1/automations';
});

/**
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function actionInput(string $action, array $config, array $extra = []): array
{
    return [
        'name' => 'Auto',
        'definition' => [
            'trigger' => ['type' => 'ticket.created', 'config' => []],
            'steps' => [['id' => 's1', 'type' => 'action', 'action' => $action, 'config' => $config]],
        ],
        ...$extra,
    ];
}

test('a lead can only move tickets to desks they can open tickets on, and an admin can to any', function () {
    actAs($this->lead, $this->workspace);
    $this->postJson($this->base, actionInput('move_desk', ['desk_id' => $this->otherDesk->id], ['desk_id' => $this->desk->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.desk_id']);
    $this->postJson($this->base, actionInput('move_desk', ['desk_id' => $this->desk->id], ['desk_id' => $this->desk->id]))->assertCreated();

    actAs($this->admin, $this->workspace);
    $this->postJson($this->base, actionInput('move_desk', ['desk_id' => $this->otherDesk->id]))->assertCreated();

    $archived = Desk::factory()->create();
    $archived->delete();
    $this->postJson($this->base, actionInput('move_desk', ['desk_id' => $archived->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.desk_id']);
});

test('assignees of a desk-scoped automation must be on its desk, and workspace-wide ones workspace members', function () {
    actAs($this->lead, $this->workspace);
    $this->postJson($this->base, actionInput('assign', ['mode' => 'add', 'user_ids' => [$this->stranger->id]], ['desk_id' => $this->desk->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.user_ids.0']);
    $this->postJson($this->base, actionInput('assign', ['mode' => 'replace', 'user_ids' => [$this->colleague->id]], ['desk_id' => $this->desk->id]))->assertCreated();

    actAs($this->admin, $this->workspace);
    $this->postJson($this->base, actionInput('assign', ['mode' => 'add', 'user_ids' => [$this->stranger->id]]))->assertCreated();
    $this->postJson($this->base, actionInput('assign', ['mode' => 'add', 'user_ids' => [User::factory()->create()->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.user_ids.0']);
});

test('a rotation must use the automation\'s desk when it is desk-scoped', function () {
    actAs($this->admin, $this->workspace);
    $scoped = fn (string $deskId) => actionInput('assign', ['mode' => 'round_robin', 'desk_id' => $deskId], ['desk_id' => $this->desk->id]);

    $this->postJson($this->base, $scoped($this->otherDesk->id))->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.desk_id']);
    $this->postJson($this->base, $scoped($this->desk->id))->assertCreated();
    $this->postJson($this->base, actionInput('assign', ['mode' => 'load_balanced', 'desk_id' => $this->otherDesk->id]))->assertCreated();
    $this->postJson($this->base, actionInput('assign', ['mode' => 'round_robin']))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.desk_id']);
});

test('action configs are validated by their config rules and reported with every error at once', function () {
    actAs($this->admin, $this->workspace);
    $field = CustomField::factory()->select(['Small', 'Large'])->create();

    $this->postJson($this->base, actionInput('set_field', ['field' => 'ticket.status', 'value' => 'bogus']))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.value']);
    $this->postJson($this->base, actionInput('set_field', ['field' => 'ticket.nope', 'value' => 'x']))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.field']);
    $this->postJson($this->base, actionInput('set_field', ['field' => "custom_field.$field->id", 'value' => 'Huge']))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.value']);
    $this->postJson($this->base, actionInput('set_field', ['field' => 'ticket.due_at', 'value' => ['relative' => ['amount' => 0, 'unit' => 'weeks']]]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.value']);
    $this->postJson($this->base, actionInput('tags', ['mode' => 'swap', 'names' => 'vip']))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.mode', 'definition.steps.s1.config.names']);
    $this->postJson($this->base, actionInput('set_reminder', ['after' => ['amount' => 0, 'unit' => 'hours']]))
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.after.amount']);

    foreach ([
        actionInput('set_field', ['field' => "custom_field.$field->id", 'value' => 'Large']),
        actionInput('set_field', ['field' => 'ticket.due_at', 'value' => ['relative' => ['amount' => 2, 'unit' => 'hours']]]),
        actionInput('tags', ['mode' => 'add', 'names' => ['vip']]),
        actionInput('set_sla_policy', ['sla_policy_id' => null]),
        actionInput('set_reminder', ['after' => ['amount' => 1, 'unit' => 'days'], 'note' => 'Look at {{ ticket.number }}']),
    ] as $input) {
        $this->postJson($this->base, $input)->assertCreated();
    }
});

test('saving an automation whose actions reference another workspace is rejected', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    [$deskB, $userB, $fieldB, $policyB, $scheduleB, $macroB] = $other->execute(fn () => [
        $default = Desk::sole(), deskMember($other, $default), CustomField::factory()->create(), SlaPolicy::factory()->create(), BusinessSchedule::factory()->create(), Macro::factory()->create(),
    ]);

    actAs($this->admin, $this->workspace);

    $rejected = [
        ['move_desk', ['desk_id' => $deskB->id], 'desk_id'],
        ['assign', ['mode' => 'add', 'user_ids' => [$userB->id]], 'user_ids.0'],
        ['notify', ['recipients' => 'users', 'user_ids' => [$userB->id], 'subject' => 'S', 'body' => 'B'], 'user_ids.0'],
        ['apply_macro', ['macro_id' => $macroB->id], 'macro_id'],
        ['assign', ['mode' => 'round_robin', 'desk_id' => $deskB->id], 'desk_id'],
        ['set_field', ['field' => "custom_field.$fieldB->id", 'value' => 'x'], 'field'],
        ['set_sla_policy', ['sla_policy_id' => $policyB->id], 'sla_policy_id'],
        ['set_reminder', ['after' => ['amount' => 1, 'unit' => 'hours', 'business_schedule_id' => $scheduleB->id]], 'after.business_schedule_id'],
        ['set_field', ['field' => 'ticket.due_at', 'value' => ['relative' => ['amount' => 1, 'unit' => 'hours', 'business_schedule_id' => $scheduleB->id]]], 'value'],
    ];

    foreach ($rejected as [$action, $config, $key]) {
        $this->postJson($this->base, actionInput($action, $config))
            ->assertUnprocessable()->assertJsonValidationErrors(["definition.steps.s1.config.$key"]);
    }

    expect(Automation::count())->toBe(0);
});

test('moving an automation to another desk re-checks its actions against the new desk', function () {
    actAs($this->admin, $this->workspace);
    $id = $this->postJson($this->base, actionInput('assign', ['mode' => 'add', 'user_ids' => [$this->colleague->id]], ['desk_id' => $this->desk->id]))
        ->assertCreated()->json('id');

    $this->putJson("$this->base/$id", ['desk_id' => $this->otherDesk->id])
        ->assertUnprocessable()->assertJsonValidationErrors(['definition.steps.s1.config.user_ids.0']);
    expect(Automation::find($id)->desk_id)->toBe($this->desk->id);

    $this->putJson("$this->base/$id", ['desk_id' => null])->assertOk();
});

test('validating an action without a known editor throws instead of skipping the editor checks', function () {
    app()->forgetInstance(EditorScope::class);

    app(DefinitionValidator::class)->errors(actionInput('move_desk', ['desk_id' => $this->desk->id])['definition']);
})->throws(LogicException::class);
