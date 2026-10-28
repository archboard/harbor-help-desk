<?php

namespace App\Data\Workspaces;

use App\Actions\Workspaces\SubdomainRules;
use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * Subdomain uniqueness and the lock once the workspace has members depend on the workspace
 * being updated, so `UpdateProvisionedWorkspace` checks them.
 */
class UpdateProvisionedWorkspaceData extends InputData
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $subdomain,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subdomain' => ['sometimes', 'required', ...SubdomainRules::FORMAT],
        ];
    }
}
