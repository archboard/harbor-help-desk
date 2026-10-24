<?php

namespace App\Data\Tickets;

use App\Data\InputData;
use App\Enums\SatisfactionScore;
use Spatie\LaravelData\Optional;

class RateTicketData extends InputData
{
    public function __construct(
        public SatisfactionScore $rating,
        public string|Optional|null $comment,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
