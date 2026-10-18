<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SendMagicLink;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class MagicLinkController extends Controller
{
    /**
     * Email a sign-in link to the given address.
     */
    public function store(Request $request, SendMagicLink $sendMagicLink): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        $sendMagicLink->handle($request->string('email')->toString());

        return back()->with('status', __('If that address has an account, a sign-in link is on its way.'));
    }

    /**
     * Sign the user in from a signed, single-use link.
     */
    public function show(Request $request, User $user): RedirectResponse
    {
        // Cache::add() only succeeds the first time, which makes each link single-use.
        $isFirstUse = fn (): bool => Cache::add('magic-link:'.$request->query('token'), true, now()->addMinutes(SendMagicLink::EXPIRES_IN_MINUTES));

        if (! $request->hasValidSignature() || ! $isFirstUse()) {
            return to_route('login')->withErrors([
                'email' => __('This sign-in link is invalid or has expired.'),
            ]);
        }

        Auth::login($user);

        // Following the emailed link proves the user owns the address.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
