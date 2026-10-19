<?php

namespace App\Http\Resources;

use App\Models\Desk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Desk
 */
class DeskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'custom_fields' => CustomFieldResource::collection($this->whenLoaded('customFields')),
            'members' => DeskMemberResource::collection($this->whenLoaded('users')),
            'archived_at' => $this->archived_at?->toIso8601String(),
        ];
    }
}
