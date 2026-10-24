<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Auth\SendMagicLink;
use App\Actions\Portal\SendPortalMagicLink;
use App\Enums\PortalAccess;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalLoginController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (Auth::guard('contact')->check()) {
            return to_route('portal.home');
        }

        return Inertia::render('portal/Login', ['status' => $request->session()->get('status')]);
    }

    /**
     * Email a sign-in link to the given address, with the same response whether or not it is a contact.
     */
    public function store(Request $request, SendPortalMagicLink $sendPortalMagicLink): RedirectResponse
    {
        $sendPortalMagicLink->handle($request->all());

        return back()->with('status', __('If that address can sign in, a sign-in link is on its way.'));
    }

    /**
     * Sign the contact in from a signed, single-use link.
     */
    public function show(Request $request): RedirectResponse
    {
        $email = (string) $request->query('email');

        // Cache::add() only succeeds the first time, which makes each link single-use.
        $isFirstUse = fn (): bool => Cache::add('portal-magic-link:'.$request->query('token'), true, now()->addMinutes(SendMagicLink::EXPIRES_IN_MINUTES));

        if (! $request->hasValidSignature() || ! $isFirstUse()) {
            return $this->invalidLink();
        }

        // The setting may have changed since the link was sent: unknown addresses only become contacts under ANYONE.
        $contact = Workspace::current()->settings->portalAccess === PortalAccess::ANYONE
            ? Contact::findOrCreateByEmail($email, Str::before($email, '@'))
            : Contact::query()->whereRaw('lower(email) = lower(?)', [$email])->first();

        if ($contact === null) {
            return $this->invalidLink();
        }

        Auth::guard('contact')->login($contact);

        $request->session()->regenerate();

        return redirect()->intended(route('portal.home'));
    }

    /**
     * Sign the contact out. The session is kept, so an agent signed in on the same browser stays signed in.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('contact')->logout();

        $request->session()->regenerateToken();

        return to_route('portal.login');
    }

    private function invalidLink(): RedirectResponse
    {
        return to_route('portal.login')->withErrors([
            'email' => __('This sign-in link is invalid or has expired.'),
        ]);
    }
}
