<?php

namespace App\Actions\Macros;

use App\Models\Automation;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

class RenderMacroBody
{
    /**
     * Fill the whitelisted placeholders with `strtr`. Unknown ones are left as typed.
     */
    public function handle(Macro $macro, Ticket $ticket, User|Automation $agent): ?string
    {
        if ($macro->body === null) {
            return null;
        }

        $requester = $ticket->requester;

        return strtr($macro->body, [
            '{{ requester.name }}' => $requester->name,
            '{{ requester.first_name }}' => $this->firstName($requester->name),
            '{{ requester.email }}' => $requester->email,
            '{{ ticket.number }}' => (string) $ticket->number,
            '{{ ticket.subject }}' => $ticket->subject,
            '{{ desk.name }}' => $ticket->desk->name,
            '{{ agent.name }}' => $agent->name,
            '{{ agent.first_name }}' => $this->firstName($agent->name),
        ]);
    }

    protected function firstName(string $name): string
    {
        return Str::before(trim($name), ' ');
    }
}
