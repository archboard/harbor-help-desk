<?php

use App\Data\Automations\ActionStepData;
use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\ConditionGroupData;
use App\Data\Automations\ConditionStepData;
use App\Data\Automations\DelayStepData;
use App\Data\Automations\PathsStepData;
use App\Data\Automations\RuleData;
use App\Data\Automations\StopStepData;
use App\Models\Automation;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

function definitionArray(): array
{
    return [
        'trigger' => ['type' => 'ticket.comment_added', 'config' => ['author' => 'requester']],
        'steps' => [
            ['id' => 'a1', 'type' => 'condition', 'match' => 'any', 'rules' => [
                ['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'is', 'value' => 'open'],
                ['type' => 'group', 'match' => 'all', 'rules' => [
                    ['type' => 'rule', 'field' => 'ticket.tags', 'operator' => 'includes_any', 'value' => ['vip']],
                    ['type' => 'rule', 'field' => 'requester.email', 'operator' => 'is_empty', 'value' => null],
                ]],
            ]],
            ['id' => 'a2', 'type' => 'action', 'action' => 'set_field', 'config' => ['field' => 'priority', 'value' => 'urgent']],
            ['id' => 'a3', 'type' => 'delay', 'for' => ['amount' => 2, 'unit' => 'hours', 'business_schedule_id' => null], 'until' => null, 'cancel_on' => ['ticket.comment_added']],
            ['id' => 'a4', 'type' => 'paths', 'branches' => [
                ['name' => 'VIP', 'condition' => ['type' => 'group', 'match' => 'all', 'rules' => [
                    ['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'value' => 'urgent'],
                ]], 'steps' => [
                    ['id' => 'b1', 'type' => 'action', 'action' => 'tags', 'config' => ['add' => ['vip']]],
                    ['id' => 'b2', 'type' => 'paths', 'branches' => [], 'else' => [['id' => 'c1', 'type' => 'stop']]],
                ]],
            ], 'else' => [['id' => 'b3', 'type' => 'stop']]],
            ['id' => 'a5', 'type' => 'stop'],
        ],
    ];
}

it('builds typed steps from an array and turns them back into the same array', function () {
    $definition = AutomationDefinitionData::validateAndCreate(definitionArray());

    expect($definition->steps[0])->toBeInstanceOf(ConditionStepData::class)
        ->and($definition->steps[0]->rules[0])->toBeInstanceOf(RuleData::class)
        ->and($definition->steps[0]->rules[1])->toBeInstanceOf(ConditionGroupData::class)
        ->and($definition->steps[0]->rules[1]->rules[0])->toBeInstanceOf(RuleData::class)
        ->and($definition->steps[1])->toBeInstanceOf(ActionStepData::class)
        ->and($definition->steps[2])->toBeInstanceOf(DelayStepData::class)
        ->and($definition->steps[3])->toBeInstanceOf(PathsStepData::class)
        ->and($definition->steps[3]->branches[0]->steps[1])->toBeInstanceOf(PathsStepData::class)
        ->and($definition->steps[3]->branches[0]->steps[1]->else[0])->toBeInstanceOf(StopStepData::class)
        ->and($definition->steps[4])->toBeInstanceOf(StopStepData::class);

    expect($definition->toArray())->toEqual(AutomationDefinitionData::from($definition->toArray())->toArray())
        ->and(json_decode(json_encode($definition), true))->toEqual(json_decode(json_encode(AutomationDefinitionData::from(definitionArray())), true));
});

it('rejects an unknown operator', function () {
    $input = definitionArray();
    $input['steps'][0]['rules'][0]['operator'] = 'sort_of';

    AutomationDefinitionData::validateAndCreate($input);
})->throws(ValidationException::class);

it('casts the stored definition of an automation', function () {
    $automation = Automation::factory()->create(['definition' => definitionArray()]);

    $reloaded = Automation::find($automation->id);

    expect($reloaded->definition)->toBeInstanceOf(AutomationDefinitionData::class)
        ->and($reloaded->definition->steps[1]->config)->toBe(['field' => 'priority', 'value' => 'urgent'])
        ->and(Automation::factory()->create()->definition->steps[0])->toBeInstanceOf(ActionStepData::class);
});
