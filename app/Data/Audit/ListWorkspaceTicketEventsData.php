<?php

namespace App\Data\Audit;

use App\Data\InputData;
use App\Enums\TicketEventType;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * Filters of the workspace-wide ticket activity list. `to` includes the whole day it names.
 */
class ListWorkspaceTicketEventsData extends InputData
{
    public function __construct(
        public TicketEventType|Optional $type,
        public string|Optional $actorId,
        public string|Optional $from,
        public string|Optional $to,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(TicketEventType::class)],
            'actor_id' => ['sometimes', 'string', 'max:40'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
