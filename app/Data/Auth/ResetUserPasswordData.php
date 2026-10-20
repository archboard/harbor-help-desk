<?php

namespace App\Data\Auth;

use App\Data\InputData;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordData extends InputData
{
    public function __construct(
        public string $password,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['password' => ['required', 'string', Password::default(), 'confirmed']];
    }
}
