<?php

namespace App\Actions\Users;

use App\Data\Users\UpdateProfileData;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateProfile
{
    /**
     * Update the user's own name, email and timezone. Changing the email makes it unverified again.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): User
    {
        $data = UpdateProfileData::validateAndCreateWith($input, [
            'email' => [Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($data->filled());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }
}
