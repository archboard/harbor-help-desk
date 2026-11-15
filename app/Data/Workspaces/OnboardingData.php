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
    /** The step keys, in order. */
    public const array STEPS = ['email', 'team', 'desk', 'first_ticket'];

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

            $done = [
                'email' => Mailbox::query()->exists(),
                'team' => $workspace->users()->count() > 1 || WorkspaceInvitation::query()->exists(),
                // ponytail: proxy for "the desk was reviewed"; add an explicit flag if it misfires
                'desk' => Desk::query()->count() > 1 || Desk::query()->whereColumn('updated_at', '>', 'created_at')->exists(),
                'first_ticket' => Ticket::withTrashed()->exists(),
            ];

            // An admin can mark a step done themselves, e.g. when the desk's defaults are fine.
            $skipped = $workspace->settings->onboardingSkippedSteps;
            $steps = array_map(fn (string $key): OnboardingStepData => new OnboardingStepData($key, $done[$key] || in_array($key, $skipped, true), in_array($key, $skipped, true)), self::STEPS);

            return new self(
                steps: $steps,
                inboundAddress: Mailbox::query()->whereNotNull('inbound_address')->oldest()->value('inbound_address'),
                defaultDeskId: $defaultDesk?->getKey(),
                complete: collect($steps)->every(fn (OnboardingStepData $step): bool => $step->complete),
            );
        });
    }
}
