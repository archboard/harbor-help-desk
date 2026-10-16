<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Agent = 'agent';

    /**
     * Owners and admins configure the whole workspace, including every desk.
     */
    public function isAdmin(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }
}
