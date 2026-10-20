<?php

namespace App\Data\Contacts;

use App\Actions\Contacts\ContactRules;
use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Max;

class CreateContactData extends InputData
{
    public function __construct(
        #[Max(255)]
        public string $name,
        public string $email,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255', ContactRules::uniqueEmail()]];
    }
}
