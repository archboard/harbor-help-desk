<?php

namespace App\Data\Workspaces;

use App\Models\Workspace;
use Spatie\LaravelData\Data;

/**
 * A workspace as the platform API sees it. `setup_url` is a fresh claim link while the workspace
 * has no owner, and null after.
 */
class ProvisionedWorkspaceData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $subdomain,
        public string $url,
        public bool $hasOwner,
        public ?string $setupUrl,
    ) {}

    public static function fromModel(Workspace $workspace): self
    {
        $hasOwner = $workspace->hasMembers();

        return new self(
            id: $workspace->id,
            name: $workspace->name,
            subdomain: $workspace->subdomain,
            url: $workspace->url(),
            hasOwner: $hasOwner,
            setupUrl: $hasOwner ? null : $workspace->setupUrl(),
        );
    }
}
