<?php

namespace App\Data\Macros;

use App\Enums\MacroScope;
use App\Models\Macro;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class MacroData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public MacroScope $scope,
        public ?string $userId,
        public ?string $deskId,
        public ?string $body,
        public bool $bodyIsInternal,
        public MacroActionsData $actions,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(Macro $macro): self
    {
        return new self(
            id: $macro->id,
            name: $macro->name,
            scope: $macro->kind(),
            userId: $macro->user_id,
            deskId: $macro->desk_id,
            body: $macro->body,
            bodyIsInternal: $macro->body_is_internal,
            actions: MacroActionsData::fromActions($macro->actions),
            createdAt: $macro->created_at,
            updatedAt: $macro->updated_at,
        );
    }
}
