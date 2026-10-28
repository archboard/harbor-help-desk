<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * ponytail: an email that already has an account is rejected; linking an existing account
 * comes with invitations.
 */
class CreateWorkspaceOwnerData extends InputData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
