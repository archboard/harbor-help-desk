<?php

namespace App\Http\Resources;

use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'ticket_comment_id' => $this->ticket_comment_id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'checksum' => $this->checksum,
            'uploader_type' => $this->uploader_type,
            'uploader_id' => $this->uploader_id,
            // API clients download with their token; the web app with its session.
            'download_url' => route($request->is('api/*') ? 'api.v1.attachments.download' : 'attachments.download', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
