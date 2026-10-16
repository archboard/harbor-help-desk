<?php

namespace App\Actions\Desks;

use App\Models\Desk;
use App\Models\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validation rules for a desk slug: URL-safe and unique among the workspace's active desks.
 */
class DeskSlugRules
{
    /**
     * @return array<int, ValidationRule|string>
     */
    public static function for(?Workspace $workspace, ?Desk $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            'alpha_dash:ascii',
            Rule::unique('desks', 'slug')
                ->where('workspace_id', $workspace?->getKey())
                ->whereNull('archived_at')
                ->ignore($ignore),
        ];
    }
}
