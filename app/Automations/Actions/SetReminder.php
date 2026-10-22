<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\SetTicketReminder;
use App\Automations\Context;
use App\Automations\PlaceholderRenderer;
use App\Data\Automations\Actions\SetReminderConfigData;
use Illuminate\Support\Str;

/**
 * Sets a ticket-level reminder for whoever is assigned, through {@see SetTicketReminder}.
 */
class SetReminder extends ConfiguredAction
{
    public function __construct(protected PlaceholderRenderer $renderer) {}

    public function key(): string
    {
        return 'set_reminder';
    }

    public function label(): string
    {
        return __('Set a reminder');
    }

    public function configData(): string
    {
        return SetReminderConfigData::class;
    }

    public function execute(Context $context, array $config): string
    {
        $config = SetReminderConfigData::from($config);
        $at = $this->after($config->after);

        app(SetTicketReminder::class)->handle($this->actor($context), $context->ticket, [
            'remind_at' => $at->toIso8601String(),
            'note' => $config->note === null ? null : Str::limit($this->renderer->render($config->note, $context->ticket, $this->actor($context)), 1000, ''),
            'for_assignees' => true,
        ]);

        return __('Reminder set for :time.', ['time' => $at->toDayDateTimeString()]);
    }

    public function describe(Context $context, array $config): string
    {
        $config = SetReminderConfigData::from($config);

        return __('Would set a reminder in :amount :unit:note.', [
            'amount' => $config->after->amount,
            'unit' => $config->after->unit->value,
            'note' => $config->note === null ? '' : ": $config->note",
        ]);
    }
}
