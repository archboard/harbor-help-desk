<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\AddTicketComment;
use App\Automations\Context;
use App\Data\Automations\Actions\AddNoteConfigData;

/**
 * Adds a templated internal note through {@see AddTicketComment}.
 */
class AddNote extends PostsComments
{
    public function key(): string
    {
        return 'add_note';
    }

    public function label(): string
    {
        return __('Add an internal note');
    }

    public function configData(): string
    {
        return AddNoteConfigData::class;
    }

    public function execute(Context $context, array $config): string
    {
        return $this->post($context, $this->render($context, AddNoteConfigData::from($config)->body, 65535), true);
    }

    public function describe(Context $context, array $config): string
    {
        return __('Would add the internal note: :body', ['body' => $this->render($context, AddNoteConfigData::from($config)->body, 200)]);
    }
}
