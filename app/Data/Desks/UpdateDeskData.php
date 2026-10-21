<?php

namespace App\Data\Desks;

use App\Actions\Desks\DeskSlugRules;
use App\Data\InputData;
use App\Enums\AssignmentStrategy;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * Slug uniqueness depends on the desk being updated, and the SLA policy must belong to its
 * workspace, so `UpdateDesk` checks both.
 */
class UpdateDeskData extends InputData
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $slug,
        public AssignmentStrategy|Optional $assignmentStrategy,
        public string|null|Optional $slaPolicyId,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', ...DeskSlugRules::FORMAT],
            'assignment_strategy' => ['sometimes', 'required', Rule::enum(AssignmentStrategy::class)],
            'sla_policy_id' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
