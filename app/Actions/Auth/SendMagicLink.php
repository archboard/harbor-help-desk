<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendMagicLink
{
    public const int EXPIRES_IN_MINUTES = 15;

    /**
     * Email a one-time sign-in link, staying silent for unknown addresses so accounts can't be enumerated.
     */
    public function handle(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        // Built during the request so the link keeps the current workspace host, which the signature covers.
        $url = URL::temporarySignedRoute('magic-link.login', now()->addMinutes(self::EXPIRES_IN_MINUTES), [
            'user' => $user->id,
            'token' => Str::random(40),
        ]);

        $user->notify(new MagicLinkNotification($url));
    }
}
