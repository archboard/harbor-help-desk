<?php

namespace App\Http;

use Inertia\Inertia;

/**
 * Flashes a toast for the next Inertia page (see resources/js/lib/flashToast.ts).
 */
class Flash
{
    public static function success(string $message): void
    {
        self::toast('success', $message);
    }

    public static function info(string $message): void
    {
        self::toast('info', $message);
    }

    public static function warning(string $message): void
    {
        self::toast('warning', $message);
    }

    public static function error(string $message): void
    {
        self::toast('error', $message);
    }

    private static function toast(string $type, string $message): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
