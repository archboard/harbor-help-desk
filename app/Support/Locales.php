<?php

namespace App\Support;

use Illuminate\Http\Request;

class Locales
{
    /**
     * The locales the app can show: English, which needs no file, plus one per `lang/{locale}.json`.
     *
     * @return array<int, string>
     */
    public static function available(): array
    {
        $files = glob(lang_path('*.json')) ?: [];

        return collect($files)
            ->map(fn (string $file): string => basename($file, '.json'))
            ->prepend('en')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The best available locale for the request's `Accept-Language` header, if any.
     */
    public static function fromRequest(Request $request): ?string
    {
        return $request->getPreferredLanguage(self::available());
    }
}
