<?php

namespace App\Data\Users;

use App\Data\InputData;
use App\Support\Locales;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

/**
 * The profile fields a user can change about themselves. That the email is unique among the
 * other users depends on who is updating, so `UpdateProfile` checks it.
 */
class UpdateProfileData extends InputData
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $email,
        public string|Optional $timezone,
        public string|Optional|null $locale,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(Locales::available())],
        ];
    }
}
