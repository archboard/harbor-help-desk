<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\PageTicketThread;
use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * Which page of a ticket's thread {@see PageTicketThread} returns.
 */
class PageTicketThreadData extends InputData
{
    public function __construct(
        public string|Optional|null $before,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['before' => ['sometimes', 'nullable', 'string', 'max:64']];
    }
}
