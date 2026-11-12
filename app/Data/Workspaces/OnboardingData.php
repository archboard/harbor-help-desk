<?php

namespace App\Data\Workspaces;

use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Spatie\LaravelData\Data;

/**
 * The "Get set up" checklist, every step computed from the workspace's real state.
 */
class OnboardingData extends Data
{
    /**
     * @param  list<OnboardingStepData>  $steps
     */
    public function __construct(
        public array $steps,
        /** The workspace's hosted inbound address, for the "send an email to" hint. */
        public ?string $inboundAddress,
        /** The oldest desk, so the checklist can link to it. */
        public ?string $defaultDeskId,
        public bool $complete,
    ) {}

    public static function forWorkspace(Workspace $workspace): self
    {
        return $workspace->execute(function () use ($workspace): self {
            $defaultDesk = Desk::query()->oldest()->orderBy('id')->first();

            $steps = [
                new OnboardingStepData('email', Mailbox::query()->exists()),
                new OnboardingStepData('team', $workspace->users()->count() > 1 || WorkspaceInvitation::query()->exists()),
                // ponytail: proxy for "the desk was reviewed"; add an explicit flag if it misfires
                new OnboardingStepData('desk', Desk::query()->count() > 1 || Desk::query()->whereColumn('updated_at', '>', 'created_at')->exists()),
                new OnboardingStepData('first_ticket', Ticket::withTrashed()->exists()),
            ];

            return new self(
                steps: $steps,
                inboundAddress: Mailbox::query()->whereNotNull('inbound_address')->oldest()->value('inbound_address'),
                defaultDeskId: $defaultDesk?->getKey(),
                complete: collect($steps)->every(fn (OnboardingStepData $step): bool => $step->complete),
            );
        });
    }
}
