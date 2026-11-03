<?php

namespace App\Data\Exports;

use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Models\Export;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

class ExportData extends Data
{
    /**
     * @param  string|null  $downloadUrl  A signed link for the browser, set while the export is ready.
     */
    public function __construct(
        public string $id,
        public ExportType $type,
        public ExportStatus $status,
        public ?string $subjectId,
        public ?string $subjectName,
        public ?string $requestedBy,
        public ?string $requestedByName,
        public ?int $size,
        public ?string $downloadUrl,
        public ?CarbonImmutable $completedAt,
        public CarbonImmutable $expiresAt,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(Export $export): self
    {
        return new self(
            id: $export->id,
            type: $export->type,
            status: $export->status,
            subjectId: $export->subject_id,
            subjectName: $export->relationLoaded('contact') ? $export->contact?->name : null,
            requestedBy: $export->requested_by,
            requestedByName: $export->relationLoaded('requester') ? $export->requester?->name : null,
            size: $export->size,
            downloadUrl: $export->isDownloadable() ? $export->signedDownloadUrl() : null,
            completedAt: $export->completed_at,
            expiresAt: $export->expires_at,
            createdAt: $export->created_at,
        );
    }
}
