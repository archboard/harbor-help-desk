<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use Illuminate\Validation\Rules\Password;

/**
 * What a new person provides when accepting an invitation. The email comes from the invitation.
 */
class AcceptInvitationData extends InputData
{
    public function __construct(
        public string $name,
        public string $password,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }
}
