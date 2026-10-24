<?php

namespace App\Data\Tickets;

use App\Models\Attachment;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class AttachmentData extends Data
{
    public function __construct(
        public string $id,
        public string $ticketId,
        public ?string $ticketCommentId,
        public string $filename,
        public string $mimeType,
        public int $size,
        public string $checksum,
        public string $uploaderType,
        public string $uploaderId,
        public string $downloadUrl,
        public ?CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(Attachment $attachment): self
    {
        return new self(
            id: $attachment->id,
            ticketId: $attachment->ticket_id,
            ticketCommentId: $attachment->ticket_comment_id,
            filename: $attachment->filename,
            mimeType: $attachment->mime_type,
            size: $attachment->size,
            checksum: $attachment->checksum,
            uploaderType: $attachment->uploader_type,
            uploaderId: $attachment->uploader_id,
            // API clients download with their token; the web app with its session; the portal as a contact.
            downloadUrl: route(match (true) {
                request()->is('api/*') => 'api.v1.attachments.download',
                request()->routeIs('portal.*') => 'portal.attachments.download',
                default => 'attachments.download',
            }, $attachment),
            createdAt: $attachment->created_at,
        );
    }
}
