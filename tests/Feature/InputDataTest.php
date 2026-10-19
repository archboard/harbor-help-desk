<?php

use App\Data\InputData;
use App\Enums\TicketPriority;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

class SampleInputData extends InputData
{
    public function __construct(
        public string $displayName,
        public TicketPriority|Optional $ticketPriority,
        public string|Optional|null $internalNote,
    ) {}
}

test('input is read and written as snake_case', function () {
    $data = SampleInputData::validateAndCreate(['display_name' => 'Jane', 'ticket_priority' => 'high']);

    expect($data->displayName)->toBe('Jane')
        ->and($data->ticketPriority)->toBe(TicketPriority::HIGH)
        ->and($data->toArray())->toBe(['display_name' => 'Jane', 'ticket_priority' => 'high']);
});

test('filled returns only the provided values, keyed by snake_case name', function () {
    expect(SampleInputData::validateAndCreate(['display_name' => 'Jane', 'internal_note' => null])->filled())
        ->toBe(['display_name' => 'Jane', 'internal_note' => null]);
});

test('invalid input throws a validation exception with snake_case keys', function () {
    expect(fn () => SampleInputData::validateAndCreate(['ticket_priority' => 'nope']))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toHaveKeys(['display_name', 'ticket_priority']);
        });
});
