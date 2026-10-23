<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\LookupTickets;
use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * What {@see LookupTickets} searches for.
 */
class LookupTicketsData extends InputData
{
    public function __construct(
        public string|Optional|null $search,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['search' => ['sometimes', 'nullable', 'string', 'max:255']];
    }
}
