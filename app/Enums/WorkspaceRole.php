<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;

enum WorkspaceRole: string
{
    use HasLabels;

    case OWNER = 'owner';
    case ADMIN = 'admin';
    case AGENT = 'agent';

    /**
     * Owners and admins configure the whole workspace, including every desk.
     */
    public function isAdmin(): bool
    {
        return $this === self::OWNER || $this === self::ADMIN;
    }

    public function label(): string
    {
        return match ($this) {
            self::OWNER => __('Owner'),
            self::ADMIN => __('Admin'),
            self::AGENT => __('Agent'),
        };
    }
}
