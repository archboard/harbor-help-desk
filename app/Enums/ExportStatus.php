<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum ExportStatus: string
{
    use HasLabels;

    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Preparing'),
            self::COMPLETED => __('Ready'),
            self::FAILED => __('Failed'),
        };
    }
}
