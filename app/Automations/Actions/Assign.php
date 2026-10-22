<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\AutoAssignTicket;
use App\Actions\Tickets\SyncTicketAssignees;
use App\Automations\AssignMode;
use App\Automations\AutomationException;
use App\Automations\Context;
use App\Data\Automations\Actions\AssignConfigData;
use App\Enums\AssignmentStrategy;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Closure;

/**
 * Adds, replaces or clears the assignees through {@see SyncTicketAssignees}, or lets a desk's
 * round robin or load-balanced rotation pick one through {@see AutoAssignTicket}. A rotation
 * only assigns open, unassigned tickets that are on the configured desk.
 */
class Assign extends ConfiguredAction
{
    public function key(): string
    {
        return 'assign';
    }

    public function label(): string
    {
        return __('Assign');
    }

    public function configData(): string
    {
        return AssignConfigData::class;
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [
            'user_ids.*' => [$this->memberRule($scope)],
            'desk_id' => [function (string $attribute, mixed $value, Closure $fail) use ($scope): void {
                if (! is_string($value) || Desk::query()->find($value) === null) {
                    $fail(__('This desk does not exist.'));
                } elseif ($scope !== null && $scope->getKey() !== $value) {
                    $fail(__('An automation scoped to a desk can only use that desk’s rotation.'));
                }
            }],
        ];
    }

    public function execute(Context $context, array $config): string
    {
        $config = AssignConfigData::from($config);
        $ticket = $context->ticket;

        if (in_array($config->mode, [AssignMode::ROUND_ROBIN, AssignMode::LOAD_BALANCED], true)) {
            $desk = $this->desk($config);

            if ($ticket->desk_id !== $desk->getKey()) {
                return __('Skipped: the ticket is not on :desk.', ['desk' => $desk->name]);
            }

            $assignee = app(AutoAssignTicket::class)->handle($ticket, $this->strategy($config->mode), $this->actor($context));

            return $assignee === null
                ? __('Nobody was assigned: the ticket is not open and unassigned, or nobody is available.')
                : __('Assigned to :name.', ['name' => $assignee->name]);
        }

        $ids = $this->userIds($config, $context);

        app(SyncTicketAssignees::class)->handle($this->actor($context), $ticket, ['user_ids' => $ids]);

        return $ids === [] ? __('Unassigned everyone.') : __('Assignees are now: :names.', ['names' => $this->names($ids)]);
    }

    public function describe(Context $context, array $config): string
    {
        $config = AssignConfigData::from($config);

        return match ($config->mode) {
            AssignMode::UNASSIGN_ALL => __('Would unassign everyone.'),
            AssignMode::ADD => __('Would add :names as assignees.', ['names' => $this->names($config->userIds ?? [])]),
            AssignMode::REPLACE => __('Would make :names the assignees.', ['names' => $this->names($config->userIds ?? [])]),
            AssignMode::ROUND_ROBIN, AssignMode::LOAD_BALANCED => __('Would assign by :strategy on :desk if the ticket is open and unassigned.', [
                'strategy' => $config->mode->label(),
                'desk' => Desk::query()->find($config->deskId)->name ?? '?',
            ]),
        };
    }

    protected function strategy(AssignMode $mode): AssignmentStrategy
    {
        return $mode === AssignMode::ROUND_ROBIN ? AssignmentStrategy::ROUND_ROBIN : AssignmentStrategy::LOAD_BALANCED;
    }

    protected function desk(AssignConfigData $config): Desk
    {
        return Desk::query()->find($config->deskId) ?? throw new AutomationException(__('The desk no longer exists.'));
    }

    /**
     * The assignees the ticket ends up with, all of them workspace members.
     *
     * @return list<string>
     */
    protected function userIds(AssignConfigData $config, Context $context): array
    {
        if ($config->mode === AssignMode::UNASSIGN_ALL) {
            return [];
        }

        $ids = $config->userIds ?? [];

        if (Workspace::current()->users()->whereKey($ids)->count() !== count(array_unique($ids))) {
            throw new AutomationException(__('A user to assign is no longer a member of the workspace.'));
        }

        if ($config->mode === AssignMode::ADD) {
            $ids = [...$context->ticket->assignees()->pluck('users.id')->all(), ...$ids];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<string>  $ids
     */
    protected function names(array $ids): string
    {
        return User::query()->whereKey($ids)->orderBy('name')->pluck('name')->implode(', ') ?: __('nobody');
    }
}
