<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Models\User;
use App\Support\Locales;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => true,
            'status' => $request->session()->get('status'),
            'timezones' => DateTimeZone::listIdentifiers(),
            'locales' => Locales::available(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request, #[CurrentUser] User $user, UpdateProfile $updateProfile): RedirectResponse
    {
        $updateProfile->handle($user, $request->only(['name', 'email', 'timezone', 'locale']));

        Flash::success(__('Profile updated.'));

        return to_route('profile.edit');
    }

    /**
     * Change only the user's timezone, e.g. from the prompt shown when their browser's
     * timezone differs, and stay on the page they were on.
     */
    public function updateTimezone(Request $request, #[CurrentUser] User $user, UpdateProfile $updateProfile): RedirectResponse
    {
        $request->validate(['timezone' => ['required']]);

        $updateProfile->handle($user, $request->only('timezone'));

        Flash::success(__('Timezone updated.'));

        return back();
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
