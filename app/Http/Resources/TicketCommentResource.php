<?php

namespace App\Http\Resources;

use App\Models\TicketComment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketComment
 */
class TicketCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'author_type' => $this->author_type,
            'author_id' => $this->author_id,
            'author_name' => $this->whenLoaded('author', fn (?Model $author) => $author?->getAttribute('name')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
