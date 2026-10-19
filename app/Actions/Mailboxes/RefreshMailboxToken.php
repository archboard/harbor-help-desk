<?php

namespace App\Actions\Mailboxes;

use App\Models\Mailbox;
use Illuminate\Support\Facades\Http;

class RefreshMailboxToken
{
    /**
     * The OAuth mailbox's access token, refreshed first when it expires within 5 minutes.
     */
    public function handle(Mailbox $mailbox): string
    {
        $settings = $mailbox->settings ?? [];

        if (($settings['expires_at'] ?? 0) > now()->addMinutes(5)->getTimestamp()) {
            return $settings['access_token'];
        }

        // ponytail: no lock, so the watcher and a send can refresh at once; both providers keep
        // the previous refresh token valid long enough. Add a cache lock if that stops holding.
        $oauth = $mailbox->driver->oauth();
        $tokens = Http::asForm()->post($oauth['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $settings['refresh_token'] ?? '',
            'client_id' => $oauth['client_id'],
            'client_secret' => $oauth['client_secret'],
        ])->throw()->json();

        $mailbox->forceFill(['settings' => [
            ...$settings,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $settings['refresh_token'],
            'expires_at' => now()->addSeconds((int) $tokens['expires_in'])->getTimestamp(),
        ]])->save();

        return $tokens['access_token'];
    }
}
