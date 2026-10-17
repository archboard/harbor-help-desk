<?php

namespace App\Http\Resources;

use App\Models\TicketEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketEvent
 */
class TicketEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'description' => $this->description(),
            'actor_type' => $this->actor_type,
            'actor_id' => $this->actor_id,
            'properties' => $this->properties,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
