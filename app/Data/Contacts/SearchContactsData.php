<?php

namespace App\Data\Contacts;

use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Optional;

class SearchContactsData extends InputData
{
    public function __construct(
        #[Max(255)]
        public string|Optional|null $search,
    ) {}
}
