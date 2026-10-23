<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\Desk;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @return array<string, mixed>
 */
function noteDefinition(string $body = 'Hello'): array
{
    return [
        'trigger' => ['type' => 'ticket.created', 'config' => []],
        'steps' => [['id' => 'note', 'type' => 'action', 'action' => 'add_note', 'config' => ['body' => $body]]],
    ];
}

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->desk = Desk::sole();
    $this->otherDesk = Desk::factory()->create(['name' => 'Billing']);
    $this->archivedDesk = Desk::factory()->create(['name' => 'Old', 'archived_at' => now()]);
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->agent = deskMember($this->workspace, $this->desk);
});

test('an admin opens the builder with every active desk and the workspace scope', function () {
    $this->actingAs($this->admin)->get(route('automations.create'))->assertInertia(fn (Assert $page) => $page
        ->component('automations/Edit')
        ->where('automation', null)
        ->where('canScopeToWorkspace', true)
        ->has('desks', 2)
        ->missing('desks.2')
        ->loadDeferredProps(fn (Assert $reloaded) => $reloaded
            ->has('catalog.triggers')
            ->has('catalog.actions')
            ->has('catalog.field_groups')));
});

test('a lead can only scope to the desks they lead', function () {
    $this->actingAs($this->lead)->get(route('automations.create'))->assertInertia(fn (Assert $page) => $page
        ->where('canScopeToWorkspace', false)
        ->has('desks', 1)
        ->where('desks.0.id', $this->desk->id));
});

test('the edit page carries the automation', function () {
    $automation = Automation::factory()->forDesk($this->desk)->create(['name' => 'Mine']);

    $this->actingAs($this->lead)->get(route('automations.edit', $automation))->assertInertia(fn (Assert $page) => $page
        ->component('automations/Edit')
        ->where('automation.id', $automation->id)
        ->where('automation.definition.steps.0.action', 'add_note'));
});

test('an agent can not open the builder or save', function () {
    $automation = Automation::factory()->forDesk($this->desk)->create();

    $this->actingAs($this->agent);
    $this->get(route('automations.create'))->assertForbidden();
    $this->get(route('automations.edit', $automation))->assertForbidden();
    $this->post(route('automations.store'), ['name' => 'x', 'definition' => noteDefinition()])->assertForbidden();
    $this->put(route('automations.update', $automation), ['name' => 'x'])->assertForbidden();
});

test('a lead can not open or change a workspace-wide automation', function () {
    $automation = Automation::factory()->create(['name' => 'Wide']);

    $this->actingAs($this->lead);
    $this->get(route('automations.edit', $automation))->assertForbidden();
    $this->put(route('automations.update', $automation), ['name' => 'Changed'])->assertForbidden();
    expect($automation->refresh()->name)->toBe('Wide');
});

test('storing creates the automation and goes to its builder, disabled unless asked', function () {
    $this->actingAs($this->admin);

    $this->post(route('automations.store'), ['name' => 'Note', 'description' => 'D', 'desk_id' => null, 'definition' => noteDefinition()])
        ->assertRedirect(route('automations.edit', Automation::query()->where('name', 'Note')->firstOrFail()));
    expect(Automation::query()->where('name', 'Note')->sole()->enabled)->toBeFalse();

    $this->post(route('automations.store'), ['name' => 'On', 'enabled' => true, 'definition' => noteDefinition()])->assertRedirect();
    expect(Automation::query()->where('name', 'On')->sole()->enabled)->toBeTrue();
});

test('a lead stores an automation on their desk but not workspace-wide', function () {
    $this->actingAs($this->lead);

    $this->post(route('automations.store'), ['name' => 'Wide', 'desk_id' => null, 'definition' => noteDefinition()])->assertForbidden();
    $this->post(route('automations.store'), ['name' => 'Mine', 'desk_id' => $this->desk->id, 'definition' => noteDefinition()])->assertRedirect();

    expect(Automation::query()->sole()->name)->toBe('Mine');
});

test('updating saves the fields and "save & enable" switches it on', function () {
    $automation = Automation::factory()->create(['name' => 'Old']);

    $this->actingAs($this->admin)->put(route('automations.update', $automation), [
        'name' => 'New', 'description' => null, 'desk_id' => null, 'definition' => noteDefinition('Changed'),
    ])->assertRedirect();
    $automation->refresh();
    expect($automation->name)->toBe('New')
        ->and($automation->enabled)->toBeFalse()
        ->and($automation->definition->steps[0]->config['body'])->toBe('Changed');

    $this->put(route('automations.update', $automation), ['name' => 'New', 'definition' => noteDefinition(), 'enabled' => true])->assertRedirect();
    expect($automation->refresh()->enabled)->toBeTrue();
});

test('save & enable leaves the automation off when the save fails', function () {
    $automation = Automation::factory()->create();

    $this->actingAs($this->admin)->put(route('automations.update', $automation), ['name' => '', 'enabled' => true])->assertSessionHasErrors('name');

    expect($automation->refresh()->enabled)->toBeFalse();
});

test('definition errors come back keyed by the step id', function () {
    $this->actingAs($this->admin)->post(route('automations.store'), [
        'name' => 'Bad',
        'definition' => [
            'trigger' => ['type' => 'schedule', 'config' => ['frequency' => 'daily']],
            'steps' => [
                ['id' => 'fine', 'type' => 'stop'],
                ['id' => 'empty-note', 'type' => 'action', 'action' => 'add_note', 'config' => []],
                ['id' => 'paths', 'type' => 'paths', 'branches' => [], 'else' => [['id' => 'nested', 'type' => 'action', 'action' => 'nope', 'config' => []]]],
            ],
        ],
    ])->assertSessionHasErrors([
        'definition.trigger.config.time',
        'definition.steps.empty-note.config.body',
        'definition.steps.paths.branches',
        'definition.steps.nested.action',
    ]);

    expect(Automation::query()->count())->toBe(0);
});

test('condition, delay and paths steps survive a save unchanged', function () {
    $definition = [
        'trigger' => ['type' => 'ticket.created', 'config' => []],
        'steps' => [
            ['id' => 'cond', 'type' => 'condition', 'match' => 'any', 'rules' => [
                ['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => 'urgent'],
                ['type' => 'group', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.subject', 'operator' => 'contains', 'value' => 'vip']]],
            ]],
            ['id' => 'wait', 'type' => 'delay', 'for' => ['amount' => 2, 'unit' => 'hours', 'business_schedule_id' => null], 'until' => null, 'cancel_on' => ['ticket.field_changed']],
            ['id' => 'split', 'type' => 'paths', 'branches' => [[
                'name' => 'Urgent',
                'condition' => ['type' => 'group', 'match' => 'all', 'rules' => [['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => 'urgent']]],
                'steps' => [['id' => 'inner', 'type' => 'stop']],
            ]], 'else' => [['id' => 'otherwise', 'type' => 'stop']]],
            ['id' => 'end', 'type' => 'stop'],
        ],
    ];
    $automation = Automation::factory()->create(['definition' => $definition]);
    $before = json_encode($automation->definition);

    // What the builder holds is the page's `automation.definition`, sent back as it came.
    $sent = $this->actingAs($this->admin)->get(route('automations.edit', $automation))->viewData('page')['props']['automation']['definition'];
    $this->put(route('automations.update', $automation), ['name' => 'Same', 'definition' => $sent])->assertRedirect()->assertSessionHasNoErrors();

    expect(json_encode($automation->refresh()->definition))->toBe($before);
});
