<?php

namespace App\Data\Automations;

use App\Models\Automation;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class AutomationData extends Data
{
    public function __construct(
        public string $id,
        public ?string $deskId,
        public string $name,
        public ?string $description,
        public bool $enabled,
        public bool $hasWebhookToken,
        public int $position,
        public string $triggerType,
        public AutomationDefinitionData $definition,
        public int $schemaVersion,
        public ?string $createdBy,
        public ?string $updatedBy,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(Automation $automation): self
    {
        return new self(
            id: $automation->id,
            deskId: $automation->desk_id,
            name: $automation->name,
            description: $automation->description,
            enabled: $automation->enabled,
            hasWebhookToken: $automation->webhook_token_hash !== null,
            position: $automation->position,
            triggerType: $automation->trigger_type,
            definition: $automation->definition,
            schemaVersion: $automation->schema_version,
            createdBy: $automation->created_by,
            updatedBy: $automation->updated_by,
            createdAt: $automation->created_at,
            updatedAt: $automation->updated_at,
        );
    }
}
