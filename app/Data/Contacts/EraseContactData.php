<?php

namespace App\Data\Contacts;

use App\Data\InputData;

/**
 * The confirmation must match the contact's current email, so `EraseContact` checks it.
 */
class EraseContactData extends InputData
{
    public function __construct(
        public string $confirm_email,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'confirm_email' => ['required', 'string'],
        ];
    }
}
