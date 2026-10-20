<?php

namespace App\Data\Tickets;

use App\Models\TicketComment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class TicketCommentData extends Data
{
    /**
     * @param  Collection<int, AttachmentData>|Lazy  $attachments
     */
    public function __construct(
        public string $id,
        public string $ticketId,
        public string $body,
        public bool $isInternal,
        public string $authorType,
        public string $authorId,
        public string|Lazy|null $authorName,
        public Collection|Lazy $attachments,
        public ?CarbonImmutable $editedAt,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(TicketComment $comment): self
    {
        return new self(
            id: $comment->id,
            ticketId: $comment->ticket_id,
            body: $comment->body,
            isInternal: $comment->is_internal,
            authorType: $comment->author_type,
            authorId: $comment->author_id,
            authorName: Lazy::whenLoaded('author', $comment, fn (): ?string => $comment->getRelation('author')?->getAttribute('name')),
            attachments: Lazy::whenLoaded('attachments', $comment, fn (): Collection => AttachmentData::collect($comment->attachments)),
            editedAt: $comment->edited_at,
            createdAt: $comment->created_at,
        );
    }
}
