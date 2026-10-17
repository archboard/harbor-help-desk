<?php

namespace App\Enums;

enum WorkspaceRole: string
{
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
}
