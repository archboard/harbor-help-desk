<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emailed to the invited address (on demand, since they may have no account yet).
 */
class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * An invitation revoked or accepted before the job runs is skipped, not failed.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public WorkspaceInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace->name;
        $inviter = $this->invitation->invitedBy?->name;

        return (new MailMessage)
            ->subject($inviter === null
                ? __('You have been invited to :workspace', ['workspace' => $workspace])
                : __(':inviter invited you to :workspace', ['inviter' => $inviter, 'workspace' => $workspace]))
            ->line(__('You have been invited to join :workspace as :role.', ['workspace' => $workspace, 'role' => $this->invitation->role->label()]))
            ->action(__('Accept invitation'), $this->invitation->acceptUrl())
            ->line(__('The link works until :date.', ['date' => $this->invitation->acceptExpiresAt()->toFormattedDayDateString()]));
    }
}
