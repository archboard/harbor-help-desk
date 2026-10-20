<?php

namespace App\Data\Tickets;

use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Max;

class UpdateTicketCommentData extends InputData
{
    public function __construct(
        #[Max(65535)]
        public string $body,
    ) {}
}
