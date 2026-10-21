<?php

namespace App\Data\SlaPolicies;

use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * The schedule must belong to the workspace, so `UpdateSlaPolicy` checks that.
 */
class UpdateSlaPolicyData extends InputData
{
    /**
     * @param  array<string, array<string, int>>|Optional  $targets
     */
    public function __construct(
        public string|Optional $name,
        public array|Optional $targets,
        public string|null|Optional $businessScheduleId,
        public int|Optional $warningPercent,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return CreateSlaPolicyData::rulesWith('sometimes');
    }
}
