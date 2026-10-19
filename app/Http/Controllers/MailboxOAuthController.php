<?php

namespace App\Http\Controllers;

use App\Actions\Mailboxes\CreateMailbox;
use App\Actions\Mailboxes\SubscribeMailboxPush;
use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connects a Google or Microsoft mailbox. The consent screen is started on the workspace's
 * host, but the provider returns to one central callback, since redirect URIs can't be
 * wildcards. An encrypted, expiring `state` carries the workspace across.
 */
class MailboxOAuthController extends Controller
{
    public function redirect(Request $request, string $provider): Response
    {
        Gate::authorize('create', Mailbox::class);

        $driver = MailboxDriver::from($provider);
        $oauth = $driver->oauth();

        abort_if(blank($oauth['client_id']), Response::HTTP_NOT_FOUND);

        $validated = Validator::make($request->query(), [
            'default_desk_id' => ['sometimes', 'string', Rule::exists('desks', 'id')->where('workspace_id', Workspace::current()?->getKey())->whereNull('archived_at')],
        ])->validate();

        $state = Crypt::encrypt([
            'workspace_id' => Workspace::current()?->getKey(),
            'default_desk_id' => $validated['default_desk_id'] ?? Workspace::current()?->desks()->oldest('id')->value('id'),
            'user_id' => $request->user()->getKey(),
            'provider' => $driver->value,
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
        ]);

        return Inertia::location($oauth['authorize_url'].'?'.Arr::query([
            'client_id' => $oauth['client_id'],
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => $oauth['scopes'],
            'state' => $state,
            // Google only returns a refresh token with offline access and a fresh consent.
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]));
    }

    public function callback(Request $request, CreateMailbox $createMailbox, SubscribeMailboxPush $subscribePush): RedirectResponse
    {
        $state = rescue(fn () => Crypt::decrypt((string) $request->query('state')), report: false);

        abort_unless(is_array($state) && ($state['expires_at'] ?? 0) > now()->getTimestamp(), Response::HTTP_FORBIDDEN);

        $workspace = Workspace::query()->whereKey($state['workspace_id'])->firstOrFail();
        $user = User::query()->whereKey($state['user_id'])->firstOrFail();
        $back = redirect()->away($workspace->url('settings/email'));

        // No code means the person declined consent.
        if (! $request->filled('code')) {
            return $back;
        }

        $driver = MailboxDriver::from($state['provider']);
        $oauth = $driver->oauth();

        $tokens = Http::asForm()->post($oauth['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $request->query('code'),
            'redirect_uri' => $this->callbackUrl(),
            'client_id' => $oauth['client_id'],
            'client_secret' => $oauth['client_secret'],
        ])->throw()->json();

        // The ID token came straight from the provider's token endpoint over TLS, so its
        // signature doesn't need checking.
        $claims = json_decode((string) base64_decode(strtr(explode('.', (string) ($tokens['id_token'] ?? ''))[1] ?? '', '-_', '+/')), true);
        $email = $claims['email'] ?? $claims['preferred_username'] ?? null;

        abort_if(blank($email), Response::HTTP_UNPROCESSABLE_ENTITY);

        $workspace->execute(fn () => $subscribePush->handle($createMailbox->handle($user, [
            'driver' => $driver->value,
            'name' => $workspace->name,
            'address' => $email,
            'default_desk_id' => $state['default_desk_id'],
            'settings' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) $tokens['expires_in'])->getTimestamp(),
            ],
        ])));

        return $back;
    }

    protected function callbackUrl(): string
    {
        return rtrim((string) config('app.url'), '/').route('mailboxes.oauth.callback', absolute: false);
    }
}
