<?php

namespace App\Data\Tickets;

use App\Data\InputData;

class SyncTicketAssigneesData extends InputData
{
    /**
     * @param  list<string>  $userIds
     */
    public function __construct(
        public array $userIds,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'user_ids' => ['present', 'array'],
            'user_ids.*' => ['string', 'distinct'],
        ];
    }
}
