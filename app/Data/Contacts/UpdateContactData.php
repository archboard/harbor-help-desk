<?php

namespace App\Data\Contacts;

use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * Email uniqueness depends on the contact being updated, so `UpdateContact` checks it.
 */
class UpdateContactData extends InputData
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $email,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
        ];
    }
}
