<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\AcceptInvitation;
use App\Data\Workspaces\WorkspaceInvitationData;
use App\Http\Flash;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    /**
     * The signed link from the invitation email. What the page offers depends on who is looking:
     * `register`, `login`, `accept`, or `mismatch` (signed in as someone else).
     */
    public function show(Request $request, WorkspaceInvitation $invitation, #[CurrentUser] ?User $user): Response
    {
        $email = mb_strtolower($invitation->email);

        $mode = match (true) {
            $user !== null && mb_strtolower($user->email) === $email => 'accept',
            $user !== null => 'mismatch',
            User::query()->whereRaw('lower(email) = ?', [$email])->exists() => 'login',
            default => 'register',
        };

        if ($mode === 'login') {
            // Fortify's login (and the magic link) send the user back here afterwards.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => WorkspaceInvitationData::from($invitation->load('invitedBy')),
            'roleLabel' => $invitation->role->label(),
            'mode' => $mode,
        ]);
    }

    /**
     * Join the workspace, creating and signing in the account first when there is none.
     */
    public function store(Request $request, WorkspaceInvitation $invitation, AcceptInvitation $acceptInvitation, #[CurrentUser] ?User $user): RedirectResponse
    {
        $accepted = $acceptInvitation->handle($invitation, $user, $request->only(['name', 'password', 'password_confirmation']));

        if ($user === null) {
            Auth::login($accepted);
            $request->session()->regenerate();
        }

        Flash::success(__('Welcome to :workspace.', ['workspace' => $invitation->workspace->name]));

        return to_route('tickets.index');
    }
}
