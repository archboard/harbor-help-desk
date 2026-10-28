<?php

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Subdomains are DNS labels of 3–63 lowercase letters, digits and inner hyphens, unique across
 * workspaces, and never one Harbor keeps for itself.
 */
class SubdomainRules
{
    /**
     * @var list<string>
     */
    public const array FORMAT = [
        'string',
        'regex:/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/',
        'not_in:www,api,app,admin,mail,help,status,docs,portal,support',
    ];

    public static function unique(?Workspace $ignore = null): Unique
    {
        return Rule::unique('workspaces', 'subdomain')->ignore($ignore);
    }
}
