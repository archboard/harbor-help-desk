<?php

namespace App\Data\CustomFields;

use App\Data\Desks\DeskData;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class CustomFieldData extends Data
{
    /**
     * @param  list<string>|null  $options
     * @param  Collection<int, DeskData>|Lazy  $desks
     */
    public function __construct(
        public string $id,
        public string $name,
        public CustomFieldType $type,
        public string $typeLabel,
        public ?array $options,
        public bool $isRequired,
        public int $position,
        public Collection|Lazy $desks,
    ) {}

    public static function fromModel(CustomField $field): self
    {
        return new self(
            id: $field->id,
            name: $field->name,
            type: $field->type,
            typeLabel: $field->type->label(),
            options: $field->options,
            isRequired: $field->is_required,
            position: $field->position,
            desks: Lazy::whenLoaded('desks', $field, fn (): Collection => DeskData::collect($field->desks)),
        );
    }
}
