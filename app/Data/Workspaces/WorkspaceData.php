<?php

namespace App\Data\Workspaces;

use App\Models\Workspace;
use Spatie\LaravelData\Data;

class WorkspaceData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $subdomain,
        public bool $isAdmin,
    ) {}

    public static function fromModel(Workspace $workspace): self
    {
        return new self(
            id: $workspace->id,
            name: $workspace->name,
            subdomain: $workspace->subdomain,
            isAdmin: request()->user()?->can('update', $workspace) ?? false,
        );
    }
}
