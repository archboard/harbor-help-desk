<?php

namespace App\Http\Resources;

use App\Data\Contacts\ContactData;
use App\Data\Desks\DeskData;
use App\Data\Tags\TagData;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'priority' => $this->priority->value,
            'priority_label' => $this->priority->label(),
            'desk_id' => $this->desk_id,
            'desk' => $this->whenLoaded('desk', fn (): DeskData => DeskData::from($this->desk)),
            'due_at' => $this->due_at?->toIso8601String(),
            'custom_fields' => (object) $this->custom_fields,
            'requester' => $this->whenLoaded('requester', fn (): ContactData => ContactData::from($this->requester)),
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'tags' => $this->whenLoaded('tags', fn (): Collection => TagData::collect($this->tags)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
