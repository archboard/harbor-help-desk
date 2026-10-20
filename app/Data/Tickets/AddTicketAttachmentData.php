<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\AddTicketAttachment;
use App\Data\InputData;
use Illuminate\Http\UploadedFile;

class AddTicketAttachmentData extends InputData
{
    public function __construct(
        public UploadedFile $file,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['file' => ['required', ...AddTicketAttachment::rules()]];
    }
}
