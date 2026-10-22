<?php

namespace App\Automations\Actions;

use App\Automations\AutomationException;
use App\Automations\Context;
use App\Automations\NotifyRecipients;
use App\Data\Automations\Actions\NotifyConfigData;
use App\Enums\DeskRole;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AutomationNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the ticket's assignees, its desk's members or leads, or chosen people with the
 * rendered template, as {@see AutomationNotice}. Each recipient's own preferences for the
 * `automation` type pick the channels. People who left the workspace or can't view the ticket are skipped.
 */
class Notify extends ConfiguredAction
{
    public function key(): string
    {
        return 'notify';
    }

    public function label(): string
    {
        return __('Send a notification');
    }

    public function configData(): string
    {
        return NotifyConfigData::class;
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return ['user_ids.*' => [$this->memberRule($scope)]];
    }

    public function execute(Context $context, array $config): string
    {
        $config = NotifyConfigData::from($config);
        $recipients = $this->recipients($config, $context->ticket);

        Notification::sendNow($recipients, new AutomationNotice(
            $context->run->automation,
            $context->ticket,
            $this->render($context, $config->subject, 200),
            $this->render($context, $config->body, 5000),
        ));

        return $recipients->isEmpty()
            ? __('Nobody to notify.')
            : __('Notified :names.', ['names' => $recipients->pluck('name')->implode(', ')]);
    }

    public function describe(Context $context, array $config): string
    {
        $config = NotifyConfigData::from($config);

        return __('Would notify :recipients: :subject', [
            'recipients' => $config->recipients->label(),
            'subject' => $this->render($context, $config->subject, 200),
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    protected function recipients(NotifyConfigData $config, Ticket $ticket): Collection
    {
        $users = match ($config->recipients) {
            NotifyRecipients::ASSIGNEES => $ticket->assignees()->get(),
            NotifyRecipients::DESK_MEMBERS => $ticket->desk->users,
            NotifyRecipients::DESK_LEADS => $ticket->desk->users->filter(fn (User $user): bool => $user->pivot->role === DeskRole::LEAD),
            NotifyRecipients::USERS => $this->chosenUsers($config->userIds ?? []),
        };

        $memberIds = Workspace::current()->users()->whereKey($users->pluck('id'))->pluck('users.id');

        return $users
            ->filter(fn (User $user): bool => $memberIds->contains($user->id) && Gate::forUser($user)->allows('view', $ticket))
            ->unique('id')->values();
    }

    /**
     * @param  list<string>  $ids
     * @return Collection<int, User>
     */
    protected function chosenUsers(array $ids): Collection
    {
        $users = Workspace::current()->users()->whereKey($ids)->get();

        if ($users->count() !== count(array_unique($ids))) {
            throw new AutomationException(__('A user to notify is no longer a member of the workspace.'));
        }

        return $users;
    }
}
