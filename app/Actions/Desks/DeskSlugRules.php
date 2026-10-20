<?php

namespace App\Actions\Desks;

use App\Models\Desk;
use App\Models\Workspace;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Desk slugs are URL-safe and unique among the workspace's active desks.
 */
class DeskSlugRules
{
    /**
     * @var list<string>
     */
    public const array FORMAT = ['string', 'max:255', 'alpha_dash:ascii'];

    public static function unique(?Workspace $workspace, ?Desk $ignore = null): Unique
    {
        return Rule::unique('desks', 'slug')
            ->where('workspace_id', $workspace?->getKey())
            ->whereNull('archived_at')
            ->ignore($ignore);
    }
}
