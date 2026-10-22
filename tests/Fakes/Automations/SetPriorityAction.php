<?php

namespace Tests\Fakes\Automations;

use App\Actions\Tickets\UpdateTicket;
use App\Automations\Context;

/**
 * Sets the ticket's priority as the running automation, like a real action does.
 */
class SetPriorityAction extends FakeAction
{
    public function key(): string
    {
        return 'fake.set_priority';
    }

    public function execute(Context $context, array $config): string
    {
        app(UpdateTicket::class)->handle($context->run->automation, $context->ticket, ['priority' => $config['priority']]);

        return "Priority set to {$config['priority']}";
    }
}
