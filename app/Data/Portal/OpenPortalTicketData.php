<?php

namespace App\Data\Portal;

use App\Actions\Tickets\AddTicketAttachment;
use App\Data\InputData;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Optional;

class OpenPortalTicketData extends InputData
{
    /**
     * @param  list<UploadedFile>|Optional  $attachments
     */
    public function __construct(
        #[Max(255)]
        public string $subject,
        public string $body,
        public array|Optional $attachments,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => AddTicketAttachment::rules(),
        ];
    }
}
