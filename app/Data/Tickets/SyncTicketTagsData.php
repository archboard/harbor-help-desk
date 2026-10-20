<?php

namespace App\Data\Tickets;

use App\Data\InputData;

class SyncTicketTagsData extends InputData
{
    /**
     * @param  list<string>  $names
     */
    public function __construct(
        public array $names,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'names' => ['present', 'array'],
            'names.*' => ['required', 'string', 'max:255'],
        ];
    }
}
