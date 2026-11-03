<?php

namespace App\Data\Audit;

use App\Data\InputData;
use App\Enums\AuditAction;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * Filters of the admin activity list. `to` includes the whole day it names.
 */
class ListAuditEventsData extends InputData
{
    public function __construct(
        public AuditAction|Optional $action,
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
            'action' => ['sometimes', Rule::enum(AuditAction::class)],
            'actor_id' => ['sometimes', 'string', 'max:40'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
