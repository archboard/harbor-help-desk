<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum MacroScope: string
{
    use HasLabels;

    case PERSONAL = 'personal';
    case WORKSPACE = 'workspace';
    case DESK = 'desk';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => __('Personal'),
            self::WORKSPACE => __('Workspace'),
            self::DESK => __('Desk'),
        };
    }
}
