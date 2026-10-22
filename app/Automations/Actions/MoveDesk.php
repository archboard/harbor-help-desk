<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\MoveTicket;
use App\Automations\AutomationException;
use App\Automations\Context;
use App\Data\Automations\Actions\MoveDeskConfigData;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Gate;

/**
 * Moves the ticket to another desk, through {@see MoveTicket}. The editor must be allowed to
 * open tickets on the target desk when the automation is saved.
 */
class MoveDesk extends ConfiguredAction
{
    public function key(): string
    {
        return 'move_desk';
    }

    public function label(): string
    {
        return __('Move to a desk');
    }

    public function configData(): string
    {
        return MoveDeskConfigData::class;
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [
            'desk_id' => [function (string $attribute, mixed $value, Closure $fail) use ($editor): void {
                $desk = is_string($value) ? Desk::query()->find($value) : null;

                if ($desk === null) {
                    $fail(__('This desk does not exist.'));
                } elseif ($editor !== null && ! Gate::forUser($editor)->allows('create', [Ticket::class, $desk])) {
                    $fail(__('You can not move tickets to this desk.'));
                }
            }],
        ];
    }

    public function execute(Context $context, array $config): string
    {
        $desk = $this->desk($config);

        app(MoveTicket::class)->handle($this->actor($context), $context->ticket, $desk);

        return __('Moved to :desk.', ['desk' => $desk->name]);
    }

    public function describe(Context $context, array $config): string
    {
        return __('Would move the ticket to :desk.', ['desk' => $this->desk($config)->name]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function desk(array $config): Desk
    {
        return Desk::query()->find(MoveDeskConfigData::from($config)->deskId)
            ?? throw new AutomationException(__('The desk no longer exists or is archived.'));
    }
}
