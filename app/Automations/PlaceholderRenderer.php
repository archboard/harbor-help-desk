<?php

namespace App\Automations;

use App\Models\Automation;
use App\Models\Ticket;
use App\Models\User;
use Closure;
use Illuminate\Support\Str;

/**
 * Fills the whitelisted `{{ key }}` placeholders of a text with `strtr`, never Blade. Unknown
 * ones are left as typed. Triggers add their own placeholders through `$extra`.
 */
class PlaceholderRenderer
{
    /**
     * The placeholders every text can use, as key => label.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'requester.name' => __('Requester name'),
            'requester.first_name' => __('Requester first name'),
            'requester.email' => __('Requester email'),
            'ticket.number' => __('Ticket number'),
            'ticket.subject' => __('Ticket subject'),
            'desk.name' => __('Desk name'),
            'agent.name' => __('Agent name'),
            'agent.first_name' => __('Agent first name'),
        ];
    }

    /**
     * @param  array<string, string>  $extra  Placeholder key => value, for what only the caller knows.
     * @param  (Closure(string): string)|null  $escape  Applied to each value before it is put in, for texts with a syntax (a URL, JSON).
     */
    public function render(string $text, Ticket $ticket, User|Automation $agent, array $extra = [], ?Closure $escape = null): string
    {
        $requester = $ticket->requester;

        $values = [
            'requester.name' => $requester->name,
            'requester.first_name' => $this->firstName($requester->name),
            'requester.email' => $requester->email,
            'ticket.number' => (string) $ticket->number,
            'ticket.subject' => $ticket->subject,
            'desk.name' => $ticket->desk->name,
            'agent.name' => $agent->name,
            'agent.first_name' => $this->firstName($agent->name),
            ...$extra,
        ];

        $replacements = [];

        foreach ($values as $key => $value) {
            $replacements['{{ '.$key.' }}'] = $escape === null ? $value : $escape($value);
        }

        return strtr($text, $replacements);
    }

    protected function firstName(string $name): string
    {
        return Str::before(trim($name), ' ');
    }
}
