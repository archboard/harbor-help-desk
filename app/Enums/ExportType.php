<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ExportType: string
{
    use HasLabels;

    case CONTACT = 'contact';
    case WORKSPACE = 'workspace';

    public function label(): string
    {
        return match ($this) {
            self::CONTACT => __('Contact data'),
            self::WORKSPACE => __('Workspace'),
        };
    }
}
