<?php

use App\Automations\FieldType;
use App\Automations\Operator;
use Carbon\CarbonImmutable;

it('applies every operator to values of the types it supports', function (Operator $operator, mixed $actual, mixed $expected, bool $result) {
    expect($operator->apply($actual, $expected))->toBe($result);
})->with([
    'is ignores case' => [Operator::IS, 'Urgent', 'urgent', true],
    'is on a different string' => [Operator::IS, 'high', 'urgent', false],
    'is on numbers' => [Operator::IS, 42, '42', true],
    'is on booleans' => [Operator::IS, true, 'true', true],
    'is on a boolean that differs' => [Operator::IS, false, true, false],
    'is on null' => [Operator::IS, null, 'urgent', false],
    'is not' => [Operator::IS_NOT, 'high', 'urgent', true],
    'is not on null' => [Operator::IS_NOT, null, 'urgent', true],
    'in' => [Operator::IN, 'High', ['urgent', 'high'], true],
    'in without a match' => [Operator::IN, 'low', ['urgent', 'high'], false],
    'not in' => [Operator::NOT_IN, 'low', ['urgent', 'high'], true],
    'contains' => [Operator::CONTAINS, 'Where is my REFUND?', 'refund', true],
    'contains without a match' => [Operator::CONTAINS, 'Hello', 'refund', false],
    'contains never matches an empty needle' => [Operator::CONTAINS, 'Hello', '', false],
    'contains on null' => [Operator::CONTAINS, null, 'refund', false],
    'not contains' => [Operator::NOT_CONTAINS, 'Hello', 'refund', true],
    'not contains on null' => [Operator::NOT_CONTAINS, null, 'refund', true],
    'starts with' => [Operator::STARTS_WITH, 'Re: Order', 're:', true],
    'starts with elsewhere' => [Operator::STARTS_WITH, 'Order Re:', 're:', false],
    'ends with' => [Operator::ENDS_WITH, 'jane@acme.test', '@ACME.test', true],
    'ends with elsewhere' => [Operator::ENDS_WITH, 'jane@acme.test', 'jane', false],
    'matches' => [Operator::MATCHES, 'Order #1234 failed', '#\d{4}', true],
    'matches ignores case' => [Operator::MATCHES, 'REFUND', '^refund$', true],
    'matches without a match' => [Operator::MATCHES, 'Hello', '^\d+$', false],
    'matches with an invalid pattern' => [Operator::MATCHES, 'Hello', '(unclosed', false],
    'matches stops at the backtrack limit' => [Operator::MATCHES, str_repeat('a', 5000).'b', '^(a+)+$', false],
    'is empty on null' => [Operator::IS_EMPTY, null, null, true],
    'is empty on an empty list' => [Operator::IS_EMPTY, [], null, true],
    'is empty on a string' => [Operator::IS_EMPTY, 'x', null, false],
    'is empty on zero' => [Operator::IS_EMPTY, 0, null, false],
    'is not empty' => [Operator::IS_NOT_EMPTY, ['vip'], null, true],
    'greater than' => [Operator::GT, 5, 3, true],
    'greater than when equal' => [Operator::GT, 3, 3, false],
    'greater than on null' => [Operator::GT, null, 3, false],
    'at least when equal' => [Operator::GTE, 3, '3', true],
    'at least on null' => [Operator::GTE, null, 3, false],
    'less than' => [Operator::LT, 2, 3, true],
    'less than on null' => [Operator::LT, null, 3, false],
    'at most when equal' => [Operator::LTE, 3, 3, true],
    'at most on null' => [Operator::LTE, null, 3, false],
    'after a date' => [Operator::GT, CarbonImmutable::parse('2026-10-22 10:00'), '2026-10-22 09:00', true],
    'before a date' => [Operator::LT, CarbonImmutable::parse('2026-10-22 10:00'), '2026-10-22 09:00', false],
    'includes any' => [Operator::INCLUDES_ANY, ['VIP', 'billing'], ['vip', 'urgent'], true],
    'includes any without a match' => [Operator::INCLUDES_ANY, ['billing'], ['vip'], false],
    'includes all' => [Operator::INCLUDES_ALL, ['vip', 'billing'], ['VIP', 'billing'], true],
    'includes all with one missing' => [Operator::INCLUDES_ALL, ['vip'], ['vip', 'billing'], false],
    'includes all of nothing' => [Operator::INCLUDES_ALL, ['vip'], [], false],
    'excludes' => [Operator::EXCLUDES, ['billing'], ['vip'], true],
    'excludes with a match' => [Operator::EXCLUDES, ['vip'], ['vip'], false],
    'changed' => [Operator::CHANGED, ['from' => 'open', 'to' => 'solved'], null, true],
    'changed without a change' => [Operator::CHANGED, null, null, false],
    'changed to' => [Operator::CHANGED_TO, ['from' => 'open', 'to' => 'solved'], 'Solved', true],
    'changed to another value' => [Operator::CHANGED_TO, ['from' => 'open', 'to' => 'pending'], 'solved', false],
    'changed to without a change' => [Operator::CHANGED_TO, null, 'solved', false],
    'changed from' => [Operator::CHANGED_FROM, ['from' => 'open', 'to' => 'solved'], 'open', true],
    'changed from another value' => [Operator::CHANGED_FROM, ['from' => 'open', 'to' => 'solved'], 'pending', false],
]);

it('declares the field types each operator supports', function (Operator $operator, FieldType $type, bool $supported) {
    expect($operator->supports($type))->toBe($supported);
})->with([
    [Operator::CONTAINS, FieldType::TEXT, true],
    [Operator::CONTAINS, FieldType::NUMBER, false],
    [Operator::IS, FieldType::BOOLEAN, true],
    [Operator::IS, FieldType::MULTI_ENUM, false],
    [Operator::INCLUDES_ANY, FieldType::MULTI_ENUM, true],
    [Operator::INCLUDES_ANY, FieldType::STRING, false],
    [Operator::GT, FieldType::DURATION, true],
    [Operator::GT, FieldType::STRING, false],
    [Operator::CHANGED_TO, FieldType::DESK, true],
    [Operator::CHANGED_TO, FieldType::TEXT, false],
]);

it('tells a valid regex from an invalid one', function () {
    expect(Operator::isValidPattern('^refund'))->toBeTrue()
        ->and(Operator::isValidPattern('a~b'))->toBeTrue()
        ->and(Operator::isValidPattern('(unclosed'))->toBeFalse()
        ->and(Operator::isValidPattern(['not a string']))->toBeFalse();
});

it('leaves the backtrack limit as it found it', function () {
    $before = ini_get('pcre.backtrack_limit');

    Operator::MATCHES->apply('abc', 'b');

    expect(ini_get('pcre.backtrack_limit'))->toBe($before);
});
