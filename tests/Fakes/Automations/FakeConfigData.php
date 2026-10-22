<?php

namespace Tests\Fakes\Automations;

use App\Data\Automations\Actions\ActionConfigData;

class FakeConfigData extends ActionConfigData
{
    public static function rules(): array
    {
        return [];
    }
}
