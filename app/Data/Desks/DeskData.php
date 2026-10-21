<?php

namespace App\Data\Desks;

use App\Data\CustomFields\CustomFieldData;
use App\Enums\AssignmentStrategy;
use App\Models\Desk;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class DeskData extends Data
{
    /**
     * @param  Collection<int, CustomFieldData>|Lazy  $customFields
     * @param  Collection<int, DeskMemberData>|Lazy  $members
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public AssignmentStrategy $assignmentStrategy,
        public ?string $slaPolicyId,
        public Collection|Lazy $customFields,
        public Collection|Lazy $members,
        public ?CarbonImmutable $archivedAt,
    ) {}

    public static function fromModel(Desk $desk): self
    {
        return new self(
            id: $desk->id,
            name: $desk->name,
            slug: $desk->slug,
            assignmentStrategy: $desk->assignment_strategy,
            slaPolicyId: $desk->sla_policy_id,
            customFields: Lazy::whenLoaded('customFields', $desk, fn (): Collection => CustomFieldData::collect($desk->customFields)),
            members: Lazy::whenLoaded('users', $desk, fn (): Collection => DeskMemberData::collect($desk->users)),
            archivedAt: $desk->archived_at,
        );
    }
}
