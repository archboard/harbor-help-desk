<?php

namespace App\Actions\Macros;

use App\Automations\PlaceholderRenderer;
use App\Models\Automation;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;

class RenderMacroBody
{
    public function __construct(protected PlaceholderRenderer $placeholders) {}

    /**
     * Fill the whitelisted placeholders. Unknown ones are left as typed.
     */
    public function handle(Macro $macro, Ticket $ticket, User|Automation $agent): ?string
    {
        if ($macro->body === null) {
            return null;
        }

        return $this->placeholders->render($macro->body, $ticket, $agent);
    }
}
