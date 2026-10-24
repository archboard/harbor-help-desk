<?php

namespace App\Data\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class WorkspaceData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $subdomain,
        public bool $isAdmin,
        public ?CarbonImmutable $awayAt,
        public Lazy|WorkspaceSettings $settings,
    ) {}

    public static function fromModel(Workspace $workspace): self
    {
        // The portal's default guard is the contact guard, and contacts aren't agents.
        $user = request()->user() instanceof User ? request()->user() : null;

        return new self(
            id: $workspace->id,
            name: $workspace->name,
            subdomain: $workspace->subdomain,
            isAdmin: $user?->can('update', $workspace) ?? false,
            awayAt: $user?->membership($workspace)?->away_at,
            settings: Lazy::when(fn (): bool => $user?->can('update', $workspace) ?? false, fn (): WorkspaceSettings => $workspace->settings),
        );
    }
}
