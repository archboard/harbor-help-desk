<?php

use App\Data\InputData;
use App\Data\Tickets\AddTicketCommentData;
use App\Data\Tickets\CreateTicketData;
use App\Data\Tickets\SearchTicketsData;
use App\Data\Tickets\UpdateTicketData;
use App\Support\DataSchema;
use Illuminate\JsonSchema\JsonSchema;

/**
 * The schema as laravel/mcp serializes a Tool's `schema()`.
 *
 * @param  class-string<InputData>  $class
 * @return array<string, mixed>
 */
function schemaOf(string $class): array
{
    return JsonSchema::object(DataSchema::for($class))->toArray();
}

test('an update has nothing required, and maps enums, nullable dates and custom fields', function () {
    $schema = schemaOf(UpdateTicketData::class);

    expect($schema)->not->toHaveKey('required')
        ->and($schema['properties']['status'])->toEqual(['type' => 'string', 'enum' => ['open', 'pending', 'on_hold', 'solved', 'closed']])
        ->and($schema['properties']['subject'])->toEqual(['type' => 'string', 'maxLength' => 255])
        ->and($schema['properties']['due_at'])->toEqual(['type' => ['string', 'null'], 'format' => 'date-time'])
        ->and($schema['properties']['custom_fields'])->toEqual(['type' => 'object', 'description' => 'Values keyed by custom field id; null removes one.']);
});

test('a create requires the subject and body', function () {
    $schema = schemaOf(CreateTicketData::class);

    expect($schema['required'])->toContain('subject')
        ->and($schema['properties']['assignee_ids'])->toEqual(['type' => 'array', 'items' => ['type' => 'string']]);
});

test('search list filters accept one value or a list', function () {
    $properties = schemaOf(SearchTicketsData::class)['properties'];
    $statuses = ['type' => 'string', 'enum' => ['open', 'pending', 'on_hold', 'solved', 'closed']];

    expect($properties['status'])->toEqual(['anyOf' => [$statuses, ['type' => 'array', 'items' => $statuses]]])
        ->and($properties['tag']['anyOf'][1])->toEqual(['type' => 'array', 'items' => ['type' => 'string']])
        ->and($properties['sort']['enum'])->toBe(['updated', 'created', 'due', 'priority', 'status', 'number', 'subject'])
        ->and($properties['direction']['enum'])->toBe(['asc', 'desc'])
        ->and($properties['deleted']['type'])->toBe('boolean');
});

test('comment parameters are described from the docblock and ids are listed', function () {
    $schema = schemaOf(AddTicketCommentData::class);

    expect($schema['required'])->toBe(['body'])
        ->and($schema['properties']['body']['maxLength'])->toBe(65535)
        ->and($schema['properties']['is_internal']['description'])->toBe('Whether the comment is only visible to agents.')
        ->and($schema['properties']['mentioned_user_ids'])->toMatchArray(['type' => 'array', 'items' => ['type' => 'string']]);
});

test('every input data class maps to a schema', function () {
    $classes = collect(File::allFiles(app_path('Data')))
        ->map(fn (SplFileInfo $file): string => 'App\\Data\\'.str($file->getRelativePathname())->beforeLast('.php')->replace('/', '\\'))
        ->filter(fn (string $class): bool => is_subclass_of($class, InputData::class));

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        expect(schemaOf($class))->toBeArray();
    }
});
