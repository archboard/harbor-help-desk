<?php

namespace App\Http\Resources;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomField
 */
class CustomFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'options' => $this->options,
            'is_required' => $this->is_required,
            'position' => $this->position,
            'desks' => DeskResource::collection($this->whenLoaded('desks')),
        ];
    }
}
