<?php

use App\Automations\Actions\AutomationAction;
use App\Automations\AutomationRegistry;
use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Automations\Triggers\Trigger;
use App\Enums\CustomFieldType;
use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Models\Automation;
use App\Models\BusinessSchedule;
use App\Models\CustomField;
use App\Models\Desk;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;
use Tests\Fakes\Automations\FakeConfigData;

class FakeMessageTrigger implements Trigger
{
    public function key(): string
    {
        return 'fake.message';
    }

    public function label(): string
    {
        return 'Message';
    }

    public function configRules(): array
    {
        return ['author' => ['sometimes', 'in:requester,agent']];
    }

    public function fieldGroups(): array
    {
        return [FieldGroup::MESSAGE];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        return true;
    }

    public function placeholders(): array
    {
        return [];
    }

    public function placeholderValues(Context $context): array
    {
        return [];
    }
}

class FakePlainTrigger extends FakeMessageTrigger
{
    public function key(): string
    {
        return 'fake.plain';
    }

    public function fieldGroups(): array
    {
        return [];
    }
}

class FakeEventTrigger extends FakeMessageTrigger
{
    public function key(): string
    {
        return 'fake.event';
    }

    public function fieldGroups(): array
    {
        return [FieldGroup::EVENT];
    }
}

class FakeNoteAction implements AutomationAction
{
    public function key(): string
    {
        return 'fake.note';
    }

    public function label(): string
    {
        return 'Note';
    }

    public function configData(): string
    {
        return FakeConfigData::class;
    }

    public function configRules(): array
    {
        return ['body' => ['required', 'string']];
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [];
    }

    public function execute(Context $context, array $config): string
    {
        return 'done';
    }

    public function describe(Context $context, array $config): string
    {
        return 'would do';
    }
}

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    app()->instance(AutomationRegistry::class, new AutomationRegistry([FakeMessageTrigger::class, FakePlainTrigger::class, FakeEventTrigger::class], [FakeNoteAction::class]));

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->otherDesk = Desk::factory()->create();
    $this->lead = deskMember($this->workspace, $this->desk, DeskRole::LEAD);
    $this->otherLead = deskMember($this->workspace, $this->otherDesk, DeskRole::LEAD);
    $this->agent = deskMember($this->workspace, $this->desk);
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->base = 'http://acme.harbor.test/api/v1/automations';
});

function noteStep(string $id = 'n1', string $body = 'Hi'): array
{
    return ['id' => $id, 'type' => 'action', 'action' => 'fake.note', 'config' => ['body' => $body]];
}

function ruleStep(string $id, array $rule, string $match = 'all'): array
{
    return ['id' => $id, 'type' => 'condition', 'match' => $match, 'rules' => [['type' => 'rule', ...$rule]]];
}

/**
 * @param  list<array<string, mixed>>  $steps
 * @return array<string, mixed>
 */
function automationInput(array $steps = [], string $trigger = 'fake.message', array $extra = []): array
{
    return ['name' => 'Auto', 'definition' => ['trigger' => ['type' => $trigger, 'config' => []], 'steps' => $steps ?: [noteStep()]], ...$extra];
}

function pathsStep(string $id, array $steps = [], array $else = []): array
{
    return [
        'id' => $id, 'type' => 'paths',
        'branches' => [['name' => 'Branch', 'condition' => ['match' => 'all', 'rules' => []], 'steps' => $steps]],
        'else' => $else,
    ];
}

test('admins create, show, update, enable and delete automations', function () {
    actAs($this->admin, $this->workspace);

    $id = $this->postJson($this->base, automationInput())
        ->assertCreated()
        ->assertJsonPath('trigger_type', 'fake.message')
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('position', 1)
        ->assertJsonPath('schema_version', 1)
        ->assertJsonPath('created_by', $this->admin->id)
        ->assertJsonPath('definition.steps.0.action', 'fake.note')
        ->json('id');
    $second = $this->postJson($this->base, automationInput(extra: ['enabled' => true]))->assertCreated()->assertJsonPath('position', 2)->assertJsonPath('enabled', true)->json('id');
    $this->postJson($this->base, automationInput(trigger: 'fake.plain'))->assertCreated()->assertJsonPath('position', 1);

    $this->getJson("$this->base/$id")->assertOk()->assertJsonPath('name', 'Auto');
    $this->getJson($this->base)->assertOk()->assertJsonCount(3)->assertJsonPath('0.id', $id)->assertJsonPath('1.id', $second);

    $this->putJson("$this->base/$id", ['name' => 'Renamed', 'definition' => automationInput(trigger: 'fake.plain')['definition']])
        ->assertOk()->assertJsonPath('name', 'Renamed')->assertJsonPath('trigger_type', 'fake.plain')->assertJsonPath('position', 2)->assertJsonPath('updated_by', $this->admin->id);
    $this->putJson("$this->base/$id/enabled", ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
    $this->putJson("$this->base/$id/enabled", [])->assertUnprocessable()->assertJsonValidationErrors('enabled');

    $this->deleteJson("$this->base/$id")->assertNoContent();
    expect(Automation::count())->toBe(2);
});

test('a lead manages automations for their own desk only', function () {
    actAs($this->lead, $this->workspace);
    $own = Automation::factory()->forDesk($this->desk)->triggeredBy('fake.message', 'fake.note', ['body' => 'Hi'])->create(['name' => 'Own']);
    $theirs = Automation::factory()->forDesk($this->otherDesk)->create();
    $wide = Automation::factory()->create();

    $this->getJson($this->base)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
    $this->postJson($this->base, automationInput(extra: ['desk_id' => $this->desk->id]))->assertCreated();
    $this->postJson($this->base, automationInput(extra: ['desk_id' => $this->otherDesk->id]))->assertForbidden();
    $this->postJson($this->base, automationInput())->assertForbidden();

    $this->putJson("$this->base/$own->id", ['name' => 'Mine'])->assertOk();
    $this->putJson("$this->base/$own->id", ['desk_id' => $this->otherDesk->id])->assertForbidden();
    $this->putJson("$this->base/$own->id", ['desk_id' => null])->assertForbidden();
    $this->putJson("$this->base/$own->id/enabled", ['enabled' => true])->assertOk();
    foreach ([$theirs, $wide] as $automation) {
        $this->getJson("$this->base/$automation->id")->assertForbidden();
        $this->putJson("$this->base/$automation->id", ['name' => 'Hacked'])->assertForbidden();
        $this->putJson("$this->base/$automation->id/enabled", ['enabled' => true])->assertForbidden();
        $this->deleteJson("$this->base/$automation->id")->assertForbidden();
    }

    expect($own->fresh())->name->toBe('Mine')->desk_id->toBe($this->desk->id)
        ->and($theirs->fresh()->name)->not->toBe('Hacked')
        ->and(Automation::count())->toBe(4);
});

test('a lead of another desk and an agent are shut out', function () {
    $automation = Automation::factory()->forDesk($this->desk)->triggeredBy('fake.message', 'fake.note', ['body' => 'Hi'])->create();

    actAs($this->otherLead, $this->workspace);
    $this->getJson("$this->base/$automation->id")->assertForbidden();
    $this->deleteJson("$this->base/$automation->id")->assertForbidden();

    actAs($this->agent, $this->workspace);
    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, automationInput(extra: ['desk_id' => $this->desk->id]))->assertForbidden();
    $this->postJson("$this->base/reorder", ['ids' => [$automation->id]])->assertForbidden();
    expect(Automation::count())->toBe(1);
});

test('a definition is checked against the registry and errors are keyed by step id', function () {
    actAs($this->admin, $this->workspace);
    $field = CustomField::factory()->create(['type' => CustomFieldType::SELECT, 'options' => ['free', 'pro']]);
    $schedule = BusinessSchedule::factory()->create();
    $rule = fn (string $field, string $operator, mixed $value = null) => compact('field', 'operator', 'value');
    $post = fn (array $steps, string $trigger = 'fake.message') => $this->postJson($this->base, automationInput($steps, $trigger));

    $post([ruleStep('c1', $rule('ticket.status', 'contains', 'x'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.operator');
    $post([ruleStep('c1', $rule('ticket.nope', 'is', 'x'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.field');
    $post([ruleStep('c1', $rule('ticket.status', 'is', 'galaxy'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('ticket.status', 'is'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('ticket.status', 'is_empty', 'x'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('ticket.subject', 'matches', '(unclosed'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('ticket.tags', 'includes_any', 'vip'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('ticket.number', 'gt', 'many'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('time.since_created', 'gt', ['amount' => 'x', 'unit' => 'weeks']))])->assertJsonValidationErrors(['definition.steps.c1.rules.0.value.amount', 'definition.steps.c1.rules.0.value.unit']);
    $post([ruleStep('c1', $rule('ticket.assignees', 'includes_any', ['usr_nobody']))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('ticket.desk', 'is', 'dsk_nobody'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule("custom_field.$field->id", 'is', 'enterprise'))])->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $post([ruleStep('c1', $rule('message.body', 'contains', 'x'))], 'fake.plain')->assertJsonValidationErrors('definition.steps.c1.rules.0.field');
    $post([['id' => 'a1', 'type' => 'action', 'action' => 'fake.nope']])->assertJsonValidationErrors('definition.steps.a1.action');
    $post([['id' => 'a1', 'type' => 'action', 'action' => 'fake.note', 'config' => []]])->assertJsonValidationErrors('definition.steps.a1.config.body');
    $post([noteStep('d'), noteStep('d')])->assertJsonValidationErrors('definition.steps.d.id');
    $post([['id' => 'x1', 'type' => 'teleport']])->assertJsonValidationErrors('definition.steps.x1.type');
    $this->postJson($this->base, ['name' => 'Auto', 'definition' => ['trigger' => ['type' => 'fake.nope'], 'steps' => []]])->assertJsonValidationErrors('definition.trigger.type');
    $this->postJson($this->base, ['name' => 'Auto', 'definition' => ['trigger' => ['type' => 'fake.message', 'config' => ['author' => 'robot']], 'steps' => []]])->assertJsonValidationErrors('definition.trigger.config.author');

    $post([['id' => 'dl', 'type' => 'delay']])->assertJsonValidationErrors('definition.steps.dl.for');
    $post([['id' => 'dl', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => 'hours'], 'until' => ['field' => 'ticket.due_at']]])->assertJsonValidationErrors('definition.steps.dl.for');
    $post([['id' => 'dl', 'type' => 'delay', 'for' => ['amount' => 0, 'unit' => 'hours', 'business_schedule_id' => 'bsc_nobody'], 'cancel_on' => ['fake.nope']]])
        ->assertJsonValidationErrors(['definition.steps.dl.for.amount', 'definition.steps.dl.for.business_schedule_id', 'definition.steps.dl.cancel_on.0']);
    $post([['id' => 'dl', 'type' => 'delay', 'until' => ['field' => 'ticket.subject']]])->assertJsonValidationErrors('definition.steps.dl.until.field');

    $post([
        ruleStep('ok1', $rule('ticket.status', 'is', 'open')),
        ruleStep('ok2', $rule("custom_field.$field->id", 'is', 'pro')),
        ruleStep('ok3', $rule('message.body', 'matches', '^refund')),
        ruleStep('ok4', $rule('time.since_created', 'gt', ['amount' => 2, 'unit' => 'days', 'business_schedule_id' => $schedule->id])),
        ruleStep('ok5', $rule('ticket.tags', 'includes_any', ['vip'])),
        ['id' => 'dl', 'type' => 'delay', 'for' => ['amount' => 2, 'unit' => 'hours'], 'cancel_on' => ['fake.message']],
        ['id' => 'dl2', 'type' => 'delay', 'until' => ['field' => 'ticket.due_at', 'offset' => -60]],
        ['id' => 'st', 'type' => 'stop'],
    ])->assertCreated();
});

test('nested paths are keyed by their own id and limited in depth and size', function () {
    actAs($this->admin, $this->workspace);
    $post = fn (array $steps) => $this->postJson($this->base, automationInput($steps));

    $post([pathsStep('p1', [pathsStep('p2', [ruleStep('deep', ['field' => 'ticket.nope', 'operator' => 'is', 'value' => 'x'])])])])
        ->assertJsonValidationErrors('definition.steps.deep.rules.0.field');
    $post([pathsStep('p1', [['id' => 'a', 'type' => 'action', 'action' => 'fake.note']])])->assertJsonValidationErrors('definition.steps.a.config.body');
    $post([pathsStep('p1', [], [['id' => 'e', 'type' => 'action', 'action' => 'fake.note']])])->assertJsonValidationErrors('definition.steps.e.config.body');

    $post([pathsStep('p1', [pathsStep('p2', [pathsStep('p3')])])])->assertCreated();
    $post([pathsStep('p1', [pathsStep('p2', [pathsStep('p3', [pathsStep('p4')])])])])->assertJsonValidationErrors('definition.steps.p4');

    $group = fn (array $inner) => ['type' => 'group', 'match' => 'any', 'rules' => [$inner]];
    $leaf = ['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'is', 'value' => 'open'];
    $post([['id' => 'c', 'type' => 'condition', 'match' => 'all', 'rules' => [$group($group($group($leaf)))]]])->assertJsonValidationErrors('definition.steps.c.rules.0.rules.0.rules.0');

    $post(array_map(fn (int $i) => noteStep("s$i"), range(1, 50)))->assertCreated();
    $post(array_map(fn (int $i) => noteStep("s$i"), range(1, 51)))->assertJsonValidationErrors('definition.steps');
    $post([pathsStep('p1', array_map(fn (int $i) => noteStep("s$i"), range(1, 50)))])->assertJsonValidationErrors('definition.steps');
    expect(Automation::count())->toBe(2);
});

test('updating validates the definition and the desk', function () {
    actAs($this->admin, $this->workspace);
    $automation = Automation::factory()->triggeredBy('fake.message', 'fake.note', ['body' => 'Hi'])->create();

    $this->putJson("$this->base/$automation->id", ['definition' => automationInput([['id' => 'a1', 'type' => 'action', 'action' => 'fake.note']])['definition']])
        ->assertJsonValidationErrors('definition.steps.a1.config.body');
    $this->putJson("$this->base/$automation->id", ['desk_id' => 'dsk_nobody', 'name' => ''])->assertJsonValidationErrors(['desk_id', 'name']);
    $this->putJson("$this->base/$automation->id", ['desk_id' => $this->otherDesk->id])->assertOk()->assertJsonPath('desk_id', $this->otherDesk->id);
});

test('reordering gives the automations their slots in the new order', function () {
    actAs($this->admin, $this->workspace);
    [$a, $b, $c] = array_map(fn (int $position) => Automation::factory()->create(['position' => $position, 'trigger_type' => 'fake.message']), [1, 2, 3]);
    $plain = Automation::factory()->create(['trigger_type' => 'fake.plain']);

    $this->postJson("$this->base/reorder", ['ids' => [$c->id, $a->id, $b->id]])->assertOk()->assertJsonPath('0.id', $c->id)->assertJsonPath('0.position', 1);
    expect(Automation::orderBy('position')->where('trigger_type', 'fake.message')->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);

    $this->postJson("$this->base/reorder", ['ids' => [$a->id, $plain->id]])->assertUnprocessable()->assertJsonValidationErrors('ids');
    $this->postJson("$this->base/reorder", ['ids' => [$a->id, $a->id]])->assertUnprocessable()->assertJsonValidationErrors('ids.1');
    $this->postJson("$this->base/reorder", ['ids' => []])->assertUnprocessable()->assertJsonValidationErrors('ids');
});

test('a lead reordering their own automations leaves the others in place', function () {
    $mineFirst = Automation::factory()->forDesk($this->desk)->create(['position' => 1]);
    $other = Automation::factory()->forDesk($this->otherDesk)->create(['position' => 2]);
    $mineLast = Automation::factory()->forDesk($this->desk)->create(['position' => 3]);

    actAs($this->lead, $this->workspace);
    $this->postJson("$this->base/reorder", ['ids' => [$mineLast->id, $mineFirst->id]])->assertOk();
    $this->postJson("$this->base/reorder", ['ids' => [$other->id, $mineFirst->id]])->assertForbidden();

    expect($mineLast->fresh()->position)->toBe(1)->and($other->fresh()->position)->toBe(2)->and($mineFirst->fresh()->position)->toBe(3);
});

test('another workspace\'s automations can be neither read nor changed', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    [$foreign, $foreignDesk, $foreignField, $foreignSchedule, $foreignUser] = $other->execute(function () use ($other) {
        $user = User::factory()->create();
        $other->users()->attach($user, ['role' => WorkspaceRole::ADMIN]);

        return [
            Automation::factory()->create(['name' => 'Foreign', 'trigger_type' => 'fake.message', 'position' => 1]),
            Desk::sole(),
            CustomField::factory()->create(['type' => CustomFieldType::TEXT]),
            BusinessSchedule::factory()->create(),
            $user,
        ];
    });
    $mine = Automation::factory()->create(['trigger_type' => 'fake.message', 'position' => 2]);
    $snapshot = $foreign->fresh()->toArray();

    actAs($this->admin, $this->workspace);
    $this->getJson($this->base)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $mine->id);
    $this->getJson("$this->base/$foreign->id")->assertNotFound();
    $this->putJson("$this->base/$foreign->id", ['name' => 'Hacked'])->assertNotFound();
    $this->putJson("$this->base/$foreign->id/enabled", ['enabled' => true])->assertNotFound();
    $this->deleteJson("$this->base/$foreign->id")->assertNotFound();
    $this->postJson("$this->base/reorder", ['ids' => [$foreign->id, $mine->id]])->assertUnprocessable()->assertJsonValidationErrors('ids.0');

    $this->postJson($this->base, automationInput(extra: ['desk_id' => $foreignDesk->id]))->assertUnprocessable()->assertJsonValidationErrors('desk_id');
    $this->putJson("$this->base/$mine->id", ['desk_id' => $foreignDesk->id])->assertUnprocessable()->assertJsonValidationErrors('desk_id');

    $rule = fn (string $field, string $operator, mixed $value) => ruleStep('c1', compact('field', 'operator', 'value'));
    $this->postJson($this->base, automationInput([$rule("custom_field.$foreignField->id", 'is', 'x')]))->assertJsonValidationErrors('definition.steps.c1.rules.0.field');
    $this->postJson($this->base, automationInput([$rule('ticket.assignees', 'includes_any', [$foreignUser->id])]))->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $this->postJson($this->base, automationInput([$rule('ticket.desk', 'is', $foreignDesk->id)]))->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $this->postJson($this->base, automationInput([$rule('time.since_created', 'gt', ['amount' => 1, 'unit' => 'days', 'business_schedule_id' => $foreignSchedule->id])]))
        ->assertJsonValidationErrors('definition.steps.c1.rules.0.value.business_schedule_id');
    $this->postJson($this->base, automationInput([['id' => 'dl', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => 'days', 'business_schedule_id' => $foreignSchedule->id]]]))
        ->assertJsonValidationErrors('definition.steps.dl.for.business_schedule_id');
    $this->putJson("$this->base/$mine->id", ['definition' => automationInput([$rule("custom_field.$foreignField->id", 'is', 'x')])['definition']])
        ->assertJsonValidationErrors('definition.steps.c1.rules.0.field');

    expect($foreign->fresh()->toArray())->toBe($snapshot)
        ->and(Automation::withoutGlobalScopes()->count())->toBe(2)
        ->and($mine->fresh()->desk_id)->toBeNull();
});

test('a token without the workspace scope is rejected', function () {
    Passport::actingAs($this->admin);

    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, automationInput())->assertForbidden();
    expect(Automation::count())->toBe(0);
});

test('a definition survives storage and comes back identical', function () {
    actAs($this->admin, $this->workspace);
    $schedule = BusinessSchedule::factory()->create();
    $duration = ['amount' => 2, 'unit' => 'days', 'business_schedule_id' => $schedule->id];
    $definition = [
        'trigger' => ['type' => 'fake.message', 'config' => ['author' => 'requester']],
        'steps' => [
            ['id' => 'c1', 'type' => 'condition', 'match' => 'any', 'rules' => [
                ['type' => 'rule', 'field' => 'ticket.tags', 'operator' => 'includes_any', 'value' => ['vip', 'urgent']],
                ['type' => 'group', 'match' => 'all', 'rules' => [
                    ['type' => 'rule', 'field' => 'time.since_created', 'operator' => 'gt', 'value' => $duration],
                    ['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'is_empty', 'value' => null],
                ]],
            ]],
            ['id' => 'd1', 'type' => 'delay', 'for' => ['amount' => 2, 'unit' => 'hours', 'business_schedule_id' => $schedule->id], 'until' => null, 'cancel_on' => ['fake.message']],
            ['id' => 'd2', 'type' => 'delay', 'for' => null, 'until' => ['field' => 'ticket.due_at', 'offset' => -60], 'cancel_on' => []],
            ['id' => 'p1', 'type' => 'paths', 'branches' => [
                ['name' => 'Branch', 'condition' => ['type' => 'group', 'match' => 'all', 'rules' => [
                    ['type' => 'group', 'match' => 'any', 'rules' => [['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'in', 'value' => ['open', 'pending']]]],
                ]], 'steps' => [noteStep('n1'), ['id' => 'st', 'type' => 'stop']]],
            ], 'else' => [noteStep('n2', 'Otherwise')]],
        ],
    ];

    $id = $this->postJson($this->base, ['name' => 'Round trip', 'definition' => $definition])->assertCreated()->json('id');

    expect(Automation::find($id)->definition->toArray())->toEqual($definition)
        ->and($this->getJson("$this->base/$id")->assertOk()->json('definition'))->toEqual($definition);
});

test('input that is not part of the contract is ignored', function () {
    actAs($this->admin, $this->workspace);
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $evil = ['id' => 'aut_hacked', 'workspace_id' => $other->id, 'position' => 99, 'trigger_type' => 'fake.plain', 'schema_version' => 7, 'created_by' => $this->agent->id, 'updated_by' => $this->agent->id, 'webhook_token_hash' => 'abc'];
    $definition = automationInput()['definition'];
    $definition['trigger']['evil'] = 'x';
    $definition['steps'][0]['evil'] = 'x';
    $definition['steps'][] = ruleStep('c1', ['field' => 'ticket.status', 'operator' => 'is', 'value' => 'open', 'evil' => 'x']);

    $id = $this->postJson($this->base, ['name' => 'Auto', 'definition' => $definition, ...$evil])->assertCreated()->json('id');
    $automation = Automation::find($id);

    expect($automation)->id->not->toBe('aut_hacked')
        ->workspace_id->toBe($this->workspace->id)
        ->position->toBe(1)
        ->trigger_type->toBe('fake.message')
        ->schema_version->toBe(1)
        ->created_by->toBe($this->admin->id)
        ->updated_by->toBe($this->admin->id)
        ->webhook_token_hash->toBeNull()
        ->and(DB::table('automations')->where('id', $id)->value('definition'))->not->toContain('evil');

    $this->putJson("$this->base/$id", ['name' => 'Renamed', ...$evil])->assertOk();
    $this->putJson("$this->base/$id", ['definition' => $definition, ...$evil, 'created_by' => $this->agent->id])->assertOk();

    expect($automation->fresh())->id->toBe($id)
        ->workspace_id->toBe($this->workspace->id)
        ->position->toBe(1)
        ->trigger_type->toBe('fake.message')
        ->schema_version->toBe(1)
        ->created_by->toBe($this->admin->id)
        ->updated_by->toBe($this->admin->id)
        ->webhook_token_hash->toBeNull();
});

test('malformed definitions are 422s, never 500s', function () {
    actAs($this->admin, $this->workspace);
    $trigger = ['type' => 'fake.message', 'config' => []];
    $post = fn (mixed $definition) => $this->postJson($this->base, ['name' => 'Auto', 'definition' => $definition])->assertUnprocessable();
    $withSteps = fn (mixed $steps) => $post(['trigger' => $trigger, 'steps' => $steps]);

    $post('nope');
    $post(['steps' => []])->assertJsonValidationErrors('definition.trigger');
    $post(['trigger' => 'fake.message', 'steps' => []])->assertJsonValidationErrors('definition.trigger');
    $post(['trigger' => ['type' => ['x']], 'steps' => []])->assertJsonValidationErrors('definition.trigger.type');
    $post(['trigger' => ['type' => 'fake.message', 'config' => 'x'], 'steps' => []])->assertJsonValidationErrors('definition.trigger.config');
    $withSteps(['a' => noteStep()])->assertJsonValidationErrors('definition.steps');
    $withSteps('nope')->assertJsonValidationErrors('definition.steps');
    $withSteps(['nope'])->assertJsonValidationErrors('definition.steps.invalid.id');
    foreach ([null, '', 123, ['x']] as $badId) {
        $withSteps([['id' => $badId, 'type' => 'stop']])->assertJsonValidationErrors('definition.steps.invalid.id');
    }
    $withSteps([['type' => 'stop']])->assertJsonValidationErrors('definition.steps.invalid.id');
    $withSteps([['id' => 'c1', 'type' => 'condition', 'match' => 'all']])->assertJsonValidationErrors('definition.steps.c1.rules');
    $withSteps([['id' => 'c1', 'type' => 'condition', 'match' => ['all'], 'rules' => ['nope']]])->assertJsonValidationErrors(['definition.steps.c1.match', 'definition.steps.c1.rules.0.type']);
    $withSteps([ruleStep('c1', ['field' => ['x'], 'operator' => ['is'], 'value' => 'x'])])->assertJsonValidationErrors(['definition.steps.c1.rules.0.field', 'definition.steps.c1.rules.0.operator']);
    $withSteps([['id' => 'p1', 'type' => 'paths']])->assertJsonValidationErrors('definition.steps.p1.branches');
    $withSteps([['id' => 'p1', 'type' => 'paths', 'branches' => []]])->assertJsonValidationErrors('definition.steps.p1.branches');
    $withSteps([['id' => 'p1', 'type' => 'paths', 'branches' => ['nope']]])->assertJsonValidationErrors('definition.steps.p1.branches.0');
    $withSteps([['id' => 'p1', 'type' => 'paths', 'branches' => [['name' => ['x'], 'steps' => 'x']], 'else' => 'x']])
        ->assertJsonValidationErrors(['definition.steps.p1.branches.0.name', 'definition.steps.p1.branches.0.condition', 'definition.steps.p1.branches.0.steps', 'definition.steps.p1.else']);
    $withSteps([['id' => 'a1', 'type' => 'action', 'action' => 'fake.note', 'config' => 'x']])->assertJsonValidationErrors('definition.steps.a1.config');
    $withSteps([['id' => 'a1', 'type' => 'action', 'action' => ['x']]])->assertJsonValidationErrors('definition.steps.a1.action');
    $withSteps([['id' => 'd1', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => 'fortnights']]])->assertJsonValidationErrors('definition.steps.d1.for.unit');
    $withSteps([['id' => 'd1', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => ['x']]]])->assertJsonValidationErrors('definition.steps.d1.for.unit');
    $withSteps([['id' => 'd1', 'type' => 'delay', 'for' => 'soon']])->assertJsonValidationErrors('definition.steps.d1.for');
    $withSteps([['id' => 'd1', 'type' => 'delay', 'until' => 'soon']])->assertJsonValidationErrors('definition.steps.d1.until');
    $withSteps([['id' => 'd1', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => 'hours'], 'cancel_on' => 'x']])->assertJsonValidationErrors('definition.steps.d1.cancel_on');
    $withSteps([['id' => 'd1', 'type' => 'delay', 'for' => ['amount' => 1, 'unit' => 'hours'], 'cancel_on' => [['x']]]])->assertJsonValidationErrors('definition.steps.d1.cancel_on.0');
    expect(Automation::count())->toBe(0);
});

test('every error is reported at once', function () {
    actAs($this->admin, $this->workspace);
    $definition = automationInput([
        ruleStep('a', ['field' => 'ticket.nope', 'operator' => 'is', 'value' => 'x']),
        pathsStep('p', [['id' => 'b', 'type' => 'action', 'action' => 'fake.note', 'config' => []]]),
    ])['definition'];

    $this->postJson($this->base, ['definition' => $definition])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'definition.steps.a.rules.0.field', 'definition.steps.b.config.body']);
});

test('step ids are unique across nesting levels', function () {
    actAs($this->admin, $this->workspace);
    $branch = fn (string $name, array $steps) => ['name' => $name, 'condition' => ['match' => 'all', 'rules' => []], 'steps' => $steps];
    $post = fn (array $steps) => $this->postJson($this->base, automationInput($steps));

    $post([noteStep('dup'), pathsStep('p1', [noteStep('dup')])])->assertJsonValidationErrors('definition.steps.dup.id');
    $post([['id' => 'p1', 'type' => 'paths', 'branches' => [$branch('A', [noteStep('x')]), $branch('B', [noteStep('x')])], 'else' => []]])->assertJsonValidationErrors('definition.steps.x.id');
    $post([pathsStep('p1', [], [noteStep('p1')])])->assertJsonValidationErrors('definition.steps.p1.id');
    expect(Automation::count())->toBe(0);
});

test('change operators need a trigger that exposes the event', function () {
    actAs($this->admin, $this->workspace);
    $post = fn (string $trigger, string $operator, mixed $value) => $this->postJson($this->base, automationInput([ruleStep('c1', ['field' => 'ticket.status', 'operator' => $operator, 'value' => $value])], $trigger));

    foreach (['changed' => null, 'changed_to' => 'solved', 'changed_from' => 'open'] as $operator => $value) {
        $post('fake.plain', $operator, $value)->assertJsonValidationErrors('definition.steps.c1.rules.0.operator');
        $post('fake.message', $operator, $value)->assertJsonValidationErrors('definition.steps.c1.rules.0.operator');
        $post('fake.event', $operator, $value)->assertCreated();
    }

    $post('fake.event', 'changed_to', 'galaxy')->assertJsonValidationErrors('definition.steps.c1.rules.0.value');
    $this->postJson($this->base, automationInput([ruleStep('c2', ['field' => 'event.to', 'operator' => 'is', 'value' => 'x'])], 'fake.event'))->assertCreated();
    expect(Automation::count())->toBe(4);
});

test('list operators need a non-empty list and the others a single value', function () {
    actAs($this->admin, $this->workspace);
    $post = fn (string $field, string $operator, mixed $value) => $this->postJson($this->base, automationInput([ruleStep('c1', compact('field', 'operator', 'value'))]));
    $invalid = 'definition.steps.c1.rules.0.value';

    $post('ticket.status', 'in', [])->assertJsonValidationErrors($invalid);
    $post('ticket.status', 'not_in', [])->assertJsonValidationErrors($invalid);
    $post('ticket.tags', 'includes_any', [])->assertJsonValidationErrors($invalid);
    $post('ticket.tags', 'excludes', [])->assertJsonValidationErrors($invalid);
    $post('ticket.status', 'in', 'open')->assertJsonValidationErrors($invalid);
    $post('ticket.status', 'in', ['open', ['x']])->assertJsonValidationErrors($invalid);
    $post('ticket.status', 'in', ['open', 'galaxy'])->assertJsonValidationErrors($invalid);
    $post('ticket.status', 'is', ['open'])->assertJsonValidationErrors($invalid);
    $post('ticket.status', 'in', ['open', 'pending'])->assertCreated();
    $post('ticket.tags', 'includes_all', ['vip'])->assertCreated();
});

test('updating changes only what it is given', function () {
    actAs($this->admin, $this->workspace);
    $create = fn (string $trigger) => Automation::find($this->postJson($this->base, automationInput(trigger: $trigger))->json('id'));
    [$a, $b, $c] = [$create('fake.message'), $create('fake.message'), $create('fake.message')];
    $plain = $create('fake.plain');
    $definition = $a->definition->toArray();

    $this->putJson("$this->base/$a->id", ['name' => 'Only the name'])->assertOk();
    expect($a->fresh())->name->toBe('Only the name')->position->toBe(1)->trigger_type->toBe('fake.message')
        ->and($a->fresh()->definition->toArray())->toEqual($definition);

    $this->putJson("$this->base/$a->id", ['definition' => automationInput([noteStep('n1', 'Changed')])['definition']])->assertOk();
    expect($a->fresh())->position->toBe(1)->and($a->fresh()->definition->steps[0]->config)->toBe(['body' => 'Changed']);

    $this->putJson("$this->base/$b->id", ['definition' => automationInput(trigger: 'fake.plain')['definition']])->assertOk()->assertJsonPath('position', 2);
    expect($plain->fresh()->position)->toBe(1);

    $this->postJson("$this->base/reorder", ['ids' => [$c->id, $a->id]])->assertOk();
    expect($c->fresh()->position)->toBe(1)->and($a->fresh()->position)->toBe(3);
    $this->postJson($this->base, automationInput())->assertCreated()->assertJsonPath('position', 4);
});

test('an admin can widen a desk automation, and archived desks are rejected', function () {
    actAs($this->admin, $this->workspace);
    $automation = Automation::factory()->forDesk($this->desk)->triggeredBy('fake.message', 'fake.note', ['body' => 'Hi'])->create();
    $archived = Desk::factory()->create(['archived_at' => now()]);

    $this->putJson("$this->base/$automation->id", ['desk_id' => $archived->id])->assertUnprocessable()->assertJsonValidationErrors('desk_id');
    $this->postJson($this->base, automationInput(extra: ['desk_id' => $archived->id]))->assertUnprocessable()->assertJsonValidationErrors('desk_id');
    expect($automation->fresh()->desk_id)->toBe($this->desk->id);

    $this->putJson("$this->base/$automation->id", ['desk_id' => null])->assertOk()->assertJsonPath('desk_id', null);
    expect($automation->fresh()->desk_id)->toBeNull();
});

test('an automation referencing a deleted custom field still loads but can not be saved again', function () {
    actAs($this->admin, $this->workspace);
    $field = CustomField::factory()->create(['type' => CustomFieldType::TEXT]);
    $definition = automationInput([ruleStep('c1', ['field' => "custom_field.$field->id", 'operator' => 'is', 'value' => 'x'])])['definition'];
    $id = $this->postJson($this->base, ['name' => 'Stale', 'definition' => $definition])->assertCreated()->json('id');

    $field->delete();

    $this->getJson("$this->base/$id")->assertOk()->assertJsonPath('definition.steps.0.rules.0.field', "custom_field.$field->id");
    $this->getJson($this->base)->assertOk()->assertJsonCount(1);
    $this->putJson("$this->base/$id", ['definition' => $definition])->assertUnprocessable()->assertJsonValidationErrors('definition.steps.c1.rules.0.field');
    $this->putJson("$this->base/$id", ['name' => 'Still renamable'])->assertOk();
});

test('enabling and reordering reject bad input', function () {
    actAs($this->admin, $this->workspace);
    [$a, $b, $c] = array_map(fn (int $position) => Automation::factory()->create(['position' => $position, 'trigger_type' => 'fake.message']), [1, 2, 3]);
    $other = Automation::factory()->create(['position' => 1, 'trigger_type' => 'fake.plain']);

    foreach (['yes', null, [], 'maybe'] as $value) {
        $this->putJson("$this->base/$a->id/enabled", ['enabled' => $value])->assertUnprocessable()->assertJsonValidationErrors('enabled');
    }
    expect($a->fresh()->enabled)->toBeFalse();

    $this->postJson("$this->base/reorder", ['ids' => [$c->id, 'aut_nobody', $a->id]])->assertUnprocessable()->assertJsonValidationErrors('ids.1');
    $this->postJson("$this->base/reorder", ['ids' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('ids');
    expect(Automation::orderBy('id')->pluck('position', 'id')->all())->toBe(collect([$a, $b, $c, $other])->sortBy('id')->mapWithKeys(fn ($automation) => [$automation->id => $automation->position])->all());

    $this->postJson("$this->base/reorder", ['ids' => [$c->id, $a->id]])->assertOk();
    expect($c->fresh()->position)->toBe(1)->and($b->fresh()->position)->toBe(2)->and($a->fresh()->position)->toBe(3)->and($other->fresh()->position)->toBe(1);
});

test('a user in two workspaces sees only the current workspace\'s automations', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    [$otherDesk, $foreign] = $other->execute(function () use ($other) {
        $other->users()->attach($this->lead, ['role' => WorkspaceRole::AGENT]);
        $desk = Desk::sole();
        $desk->users()->attach($this->lead, ['role' => DeskRole::LEAD, 'workspace_id' => $other->id]);

        return [$desk, Automation::factory()->forDesk($desk)->create(['name' => 'Foreign'])];
    });
    $mine = Automation::factory()->forDesk($this->desk)->create(['name' => 'Mine']);

    actAs($this->lead, $this->workspace);
    $this->getJson($this->base)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $mine->id);
    $this->getJson("$this->base/$foreign->id")->assertNotFound();
    $this->putJson("$this->base/$foreign->id", ['name' => 'Hacked'])->assertNotFound();
    $this->postJson($this->base, automationInput(extra: ['desk_id' => $otherDesk->id]))->assertUnprocessable()->assertJsonValidationErrors('desk_id');

    Workspace::forgetCurrent();
    actAs($this->lead, $other);
    $theirs = 'http://other.harbor.test/api/v1/automations';
    $this->getJson($theirs)->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $foreign->id);
    $this->getJson("$theirs/$mine->id")->assertNotFound();
    $this->postJson($theirs, automationInput(extra: ['desk_id' => $this->desk->id]))->assertUnprocessable()->assertJsonValidationErrors('desk_id');

    expect($foreign->fresh()->name)->toBe('Foreign')->and(Automation::withoutGlobalScopes()->count())->toBe(2);
});
