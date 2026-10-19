<?php

namespace App\Http\Resources;

use App\Models\SavedView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedView
 */
class SavedViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'owner' => new UserResource($this->whenLoaded('user')),
            'name' => $this->name,
            // An object even when empty, so clients always get a map of filter => value.
            'filters' => (object) $this->filters,
            'is_shared' => $this->is_shared,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
