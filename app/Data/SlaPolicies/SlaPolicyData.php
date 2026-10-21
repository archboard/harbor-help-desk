<?php

namespace App\Data\SlaPolicies;

use App\Data\BusinessSchedules\BusinessScheduleData;
use App\Models\SlaPolicy;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class SlaPolicyData extends Data
{
    /**
     * @param  array<string, array<string, int>>  $targets
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $businessScheduleId,
        public int $warningPercent,
        public array $targets,
        public BusinessScheduleData|Lazy|null $businessSchedule,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(SlaPolicy $slaPolicy): self
    {
        return new self(
            id: $slaPolicy->id,
            name: $slaPolicy->name,
            businessScheduleId: $slaPolicy->business_schedule_id,
            warningPercent: $slaPolicy->warning_percent,
            targets: $slaPolicy->targets,
            businessSchedule: Lazy::whenLoaded('businessSchedule', $slaPolicy, fn (): ?BusinessScheduleData => $slaPolicy->businessSchedule ? BusinessScheduleData::from($slaPolicy->businessSchedule) : null),
            createdAt: $slaPolicy->created_at,
            updatedAt: $slaPolicy->updated_at,
        );
    }
}
