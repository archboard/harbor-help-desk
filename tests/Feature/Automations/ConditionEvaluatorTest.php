<?php

use App\Automations\ConditionEvaluator;
use App\Automations\Context;
use App\Automations\MatchMode;
use App\Data\Automations\ConditionGroupData;
use App\Data\Automations\ConditionStepData;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Events\TicketActivityRecorded;
use App\Models\BusinessSchedule;
use App\Models\CustomField;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

/**
 * @param  list<array<string, mixed>>  $rules
 * @return array<string, mixed>
 */
function evaluate(string $match, array $rules, Context $context): array
{
    $step = ConditionStepData::from(['id' => 's1', 'type' => 'condition', 'match' => $match, 'rules' => $rules]);

    return app(ConditionEvaluator::class)->evaluate($step->match, $step->rules, $context);
}

function rule(string $field, string $operator, mixed $value = null): array
{
    return ['type' => 'rule', 'field' => $field, 'operator' => $operator, 'value' => $value];
}

beforeEach(function () {
    Event::fake([TicketActivityRecorded::class]);
    $this->ticket = Ticket::factory()->create(['subject' => 'Refund please', 'priority' => TicketPriority::URGENT, 'due_at' => '2026-10-22 10:00']);
    $this->context = new Context($this->ticket);
});

it('passes all rules or any rule and logs what each compared', function (string $match, bool $passed) {
    $result = evaluate($match, [
        rule('ticket.priority', 'is', 'urgent'),
        rule('ticket.subject', 'contains', 'invoice'),
    ], $this->context);

    expect($result['passed'])->toBe($passed)
        ->and($result['match'])->toBe($match)
        ->and($result['rules'])->toEqual([
            ['type' => 'rule', 'field' => 'ticket.priority', 'operator' => 'is', 'expected' => 'urgent', 'actual' => 'urgent', 'passed' => true],
            ['type' => 'rule', 'field' => 'ticket.subject', 'operator' => 'contains', 'expected' => 'invoice', 'actual' => 'Refund please', 'passed' => false],
        ]);
})->with([
    'all' => ['all', false],
    'any' => ['any', true],
]);

it('evaluates nested groups and logs them', function () {
    $result = evaluate('all', [
        rule('ticket.priority', 'is', 'urgent'),
        ['type' => 'group', 'match' => 'any', 'rules' => [
            rule('ticket.subject', 'starts_with', 'nope'),
            ['type' => 'group', 'match' => 'all', 'rules' => [
                rule('ticket.subject', 'ends_with', 'please'),
                rule('requester.email_domain', 'is_not_empty'),
            ]],
        ]],
    ], $this->context);

    expect($result['passed'])->toBeTrue()
        ->and($result['rules'][1]['type'])->toBe('group')
        ->and($result['rules'][1]['passed'])->toBeTrue()
        ->and($result['rules'][1]['rules'][0]['passed'])->toBeFalse()
        ->and($result['rules'][1]['rules'][1]['rules'])->toHaveCount(2)
        ->and($result['rules'][1]['rules'][1]['passed'])->toBeTrue();

    $failing = evaluate('all', [
        rule('ticket.priority', 'is', 'urgent'),
        ['type' => 'group', 'match' => 'any', 'rules' => [rule('ticket.subject', 'starts_with', 'nope')]],
    ], $this->context);

    expect($failing['passed'])->toBeFalse();
});

it('passes a group without rules', function () {
    expect(evaluate('any', [], $this->context)['passed'])->toBeTrue();
});

it('evaluates a branch condition group', function () {
    $group = ConditionGroupData::from(['type' => 'group', 'match' => 'all', 'rules' => [rule('ticket.number', 'gte', 1)]]);

    expect(app(ConditionEvaluator::class)->evaluate($group->match, $group->rules, $this->context)['passed'])->toBeTrue()
        ->and($group->match)->toBe(MatchMode::ALL);
});

it('fails a rule with an error instead of throwing', function (array $rule, string $error) {
    $result = evaluate('all', [$rule], $this->context);

    expect($result['passed'])->toBeFalse()
        ->and($result['rules'][0]['passed'])->toBeFalse()
        ->and($result['rules'][0]['error'])->toContain($error);
})->with([
    'unknown field' => [rule('ticket.nope', 'is', 'x'), 'Unknown field'],
    'incompatible operator' => [rule('ticket.priority', 'gt', 3), 'can’t be used'],
    'unparsable date' => [rule('ticket.due_at', 'gt', 'not a date'), ''],
]);

it('compares durations in business time when the rule names a schedule', function () {
    $this->travelTo('2026-10-21 10:00');
    $this->ticket->forceFill(['created_at' => '2026-10-20 16:00'])->saveQuietly();
    $schedule = BusinessSchedule::factory()->create();
    $context = new Context($this->ticket);

    $duration = fn (string $operator, ?string $scheduleId) => rule('time.since_created', $operator, ['amount' => 2, 'unit' => 'hours', 'business_schedule_id' => $scheduleId]);

    // 16:00-17:00 Tuesday plus 09:00-10:00 Wednesday is 120 business minutes; 18 hours elapsed.
    expect(evaluate('all', [$duration('gte', $schedule->id)], $context)['rules'][0])->toMatchArray(['actual' => 120, 'passed' => true])
        ->and(evaluate('all', [$duration('gt', $schedule->id)], $context)['passed'])->toBeFalse()
        ->and(evaluate('all', [$duration('gt', null)], $context)['rules'][0])->toMatchArray(['actual' => 1080, 'passed' => true]);
});

it('reads the change for the changed operators', function (string $operator, mixed $value, bool $passed) {
    $event = $this->ticket->recordEvent(TicketEventType::PRIORITY_CHANGED, null, ['from' => 'normal', 'to' => 'urgent']);
    $context = new Context($this->ticket, $event);

    expect(evaluate('all', [rule('ticket.priority', $operator, $value)], $context)['passed'])->toBe($passed);
})->with([
    ['changed', null, true],
    ['changed_to', 'urgent', true],
    ['changed_to', 'low', false],
    ['changed_from', 'normal', true],
    ['changed_from', 'urgent', false],
]);

it('fails the changed operators for a field the event did not change', function () {
    $event = $this->ticket->recordEvent(TicketEventType::PRIORITY_CHANGED, null, ['from' => 'normal', 'to' => 'urgent']);

    expect(evaluate('all', [rule('ticket.status', 'changed')], new Context($this->ticket, $event))['passed'])->toBeFalse()
        ->and(evaluate('all', [rule('ticket.status', 'changed')], $this->context)['passed'])->toBeFalse();
});

it('evaluates custom fields of the current workspace and not another one', function () {
    $field = CustomField::factory()->select()->create();
    $this->ticket->update(['custom_fields' => [$field->id => 'Large']]);
    $rule = rule("custom_field.$field->id", 'is', 'large');

    expect(evaluate('all', [$rule], new Context($this->ticket))['passed'])->toBeTrue();

    Workspace::factory()->create()->makeCurrent();
    $other = new Context(Ticket::factory()->create());
    $result = evaluate('all', [$rule], $other);

    expect($result['passed'])->toBeFalse()->and($result['rules'][0]['error'])->toContain('Unknown field');
});
