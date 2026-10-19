<?php

namespace App\Listeners;

use App\Models\User;
use DateTimeZone;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Give a user without a timezone the one their browser sent with the login form. After that,
 * the app prompts them when their browser disagrees, and the profile page changes it.
 */
class StoreTimezoneFromLogin
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        $timezone = $this->request->input('timezone');

        if (! $event->user instanceof User || $event->user->timezone !== null || ! is_string($timezone)) {
            return;
        }

        if (in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $event->user->update(['timezone' => $timezone]);
        }
    }
}
