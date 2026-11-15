<?php

namespace App\Data\Workspaces;

use App\Data\Desks\DeskData;
use App\Data\Users\UserData;
use App\Enums\WorkspaceRole;
use App\Models\WorkspaceInvitation;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class WorkspaceInvitationData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public WorkspaceRole $role,
        public DeskData|Lazy|null $desk,
        public UserData|Lazy|null $invitedBy,
        public ?CarbonImmutable $createdAt,
        public CarbonImmutable $expiresAt,
    ) {}

    public static function fromModel(WorkspaceInvitation $invitation): self
    {
        return new self(
            id: $invitation->id,
            email: $invitation->email,
            role: $invitation->role,
            desk: Lazy::whenLoaded('desk', $invitation, fn (): ?DeskData => $invitation->desk ? DeskData::from($invitation->desk) : null),
            invitedBy: Lazy::whenLoaded('invitedBy', $invitation, fn (): ?UserData => $invitation->invitedBy ? UserData::from($invitation->invitedBy) : null),
            createdAt: $invitation->created_at,
            expiresAt: $invitation->acceptExpiresAt(),
        );
    }
}
