<?php

namespace App\Data\Portal;

use App\Data\InputData;

class SendPortalMagicLinkData extends InputData
{
    public function __construct(
        public string $email,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }
}
