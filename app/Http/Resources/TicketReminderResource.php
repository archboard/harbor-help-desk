<?php

namespace App\Http\Resources;

use App\Models\TicketReminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketReminder
 */
class TicketReminderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'user_id' => $this->user_id,
            'for_assignees' => $this->isTicketLevel(),
            'created_by' => $this->created_by,
            'remind_at' => $this->remind_at->toIso8601String(),
            'note' => $this->note,
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
