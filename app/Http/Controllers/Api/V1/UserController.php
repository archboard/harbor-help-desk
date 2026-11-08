<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\UpdateProfile;
use App\Data\Users\UserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(#[CurrentUser] User $user): UserData
    {
        return UserData::from($user);
    }

    /**
     * Update the caller's own profile and timezone.
     */
    public function update(Request $request, #[CurrentUser] User $user, UpdateProfile $updateProfile): UserData
    {
        return UserData::from($updateProfile->handle($user, $request->all()));
    }
}
