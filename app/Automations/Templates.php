<?php

namespace App\Automations;

use App\Data\Automations\AutomationDefinitionData;
use App\Data\Automations\AutomationTemplateData;
use App\Models\Desk;
use InvalidArgumentException;

/**
 * Ready-made automation definitions, in the saved shape. Step ids are fixed and readable (unique
 * within each definition). Workspace-specific values are editable examples (a keyword, a domain,
 * a URL), except the desk of "route by requester domain": it is `$deskId` (the automation's scope
 * desk) or else the workspace's first desk, so the template validates as it is. The editor
 * is expected to adjust it before enabling, and templates are always created disabled.
 */
class Templates
{
    public const array KEYS = ['auto_close_stale_pending', 'tag_by_keyword', 'route_by_requester_domain', 'escalate_on_sla_breach', 'notify_leads_of_urgent', 'acknowledge_new_email', 'forward_urgent_to_webhook'];

    public static function exists(mixed $key): bool
    {
        return is_string($key) && in_array($key, self::KEYS, true);
    }

    /**
     * @return list<AutomationTemplateData>
     */
    public static function all(?string $deskId = null): array
    {
        return array_map(fn (string $key): AutomationTemplateData => self::find($key, $deskId), self::KEYS);
    }

    /**
     * @param  string|null  $deskId  Fills the desk of templates that need one. Empty when null.
     */
    public static function find(string $key, ?string $deskId = null): AutomationTemplateData
    {
        [$name, $description, $category, $definition] = self::spec($key, $deskId ?? '');

        return new AutomationTemplateData($key, $name, $description, $category, $definition['trigger']['type'], AutomationDefinitionData::from($definition));
    }

    /**
     * The definition to create an automation from, with a valid desk in the current workspace.
     *
     * @return array<string, mixed>
     */
    public static function definitionFor(string $key, ?string $scopeDeskId): array
    {
        $deskId = $scopeDeskId ?? Desk::query()->orderBy('id')->value('id');

        return self::spec($key, (string) $deskId)[3];
    }

    /**
     * @return array{string, string, string, array<string, mixed>}
     */
    protected static function spec(string $key, string $deskId): array
    {
        self::exists($key) || throw new InvalidArgumentException("Unknown automation template [$key].");

        $condition = fn (string $field, string $operator, mixed $value = null, string $match = 'all'): array => [
            'id' => 'condition', 'type' => 'condition', 'match' => $match,
            'rules' => [['type' => 'rule', 'field' => $field, 'operator' => $operator, 'value' => $value]],
        ];
        $action = fn (string $id, string $action, array $config): array => ['id' => $id, 'type' => 'action', 'action' => $action, 'config' => $config];
        $urgent = fn (): array => ['type' => 'ticket.field_changed', 'config' => ['fields' => ['ticket.priority']]];

        return match ($key) {
            'auto_close_stale_pending' => [
                __('Close stale pending tickets'),
                __('Every day, close pending tickets that have had no update for a week.'),
                __('Housekeeping'),
                ['trigger' => ['type' => 'schedule', 'config' => ['frequency' => 'daily', 'time' => '09:00', 'timezone' => 'UTC']], 'steps' => [
                    [
                        'id' => 'condition', 'type' => 'condition', 'match' => 'all', 'rules' => [
                            ['type' => 'rule', 'field' => 'ticket.status', 'operator' => 'is', 'value' => 'pending'],
                            ['type' => 'rule', 'field' => 'time.since_updated', 'operator' => 'gt', 'value' => ['amount' => 7, 'unit' => 'days']],
                        ],
                    ],
                    $action('close', 'set_field', ['field' => 'ticket.status', 'value' => 'closed']),
                ]],
            ],
            'tag_by_keyword' => [
                __('Tag by keyword'),
                __('Tag a ticket when the requester writes a message that mentions a keyword.'),
                __('Triage'),
                ['trigger' => ['type' => 'ticket.comment_added', 'config' => ['author' => 'requester']], 'steps' => [
                    $condition('message.body', 'contains', 'refund'),
                    $action('tag', 'tags', ['mode' => 'add', 'names' => ['refund']]),
                ]],
            ],
            'route_by_requester_domain' => [
                __('Route by requester domain'),
                __('Move new tickets from a company\'s email domain to a desk.'),
                __('Routing'),
                ['trigger' => ['type' => 'ticket.created', 'config' => []], 'steps' => [
                    $condition('requester.email_domain', 'is', 'example.com'),
                    $action('move', 'move_desk', ['desk_id' => $deskId]),
                ]],
            ],
            'escalate_on_sla_breach' => [
                __('Escalate on SLA breach'),
                __('Make the ticket urgent and tell the desk leads when an SLA is breached.'),
                __('SLA'),
                ['trigger' => ['type' => 'ticket.sla', 'config' => ['kind' => 'breached']], 'steps' => [
                    [
                        'id' => 'condition', 'type' => 'condition', 'match' => 'any', 'rules' => [
                            ['type' => 'rule', 'field' => 'sla.first_response', 'operator' => 'is', 'value' => 'breached'],
                            ['type' => 'rule', 'field' => 'sla.resolution', 'operator' => 'is', 'value' => 'breached'],
                        ],
                    ],
                    $action('urgent', 'set_field', ['field' => 'ticket.priority', 'value' => 'urgent']),
                    $action('notify', 'notify', ['recipients' => 'desk_leads', 'subject' => 'SLA breached on #{{ ticket.number }}', 'body' => '{{ ticket.subject }} breached its SLA and is now urgent.']),
                ]],
            ],
            'notify_leads_of_urgent' => [
                __('Notify leads of urgent tickets'),
                __('Tell the desk leads when a ticket becomes urgent.'),
                __('Notifications'),
                ['trigger' => $urgent(), 'steps' => [
                    $condition('ticket.priority', 'changed_to', 'urgent'),
                    $action('notify', 'notify', ['recipients' => 'desk_leads', 'subject' => 'Urgent ticket #{{ ticket.number }}', 'body' => '{{ ticket.subject }} is now urgent.']),
                ]],
            ],
            'acknowledge_new_email' => [
                __('Acknowledge new email tickets'),
                __('Reply to new tickets that arrive by email to say they were received.'),
                __('Replies'),
                ['trigger' => ['type' => 'ticket.created', 'config' => ['sources' => ['email']]], 'steps' => [
                    $condition('ticket.source', 'is', 'email'),
                    $action('reply', 'send_reply', ['body' => 'Thanks for getting in touch. We received your message and will get back to you soon.']),
                ]],
            ],
            default => [
                __('Forward urgent tickets to a webhook'),
                __('Send a JSON request to another system when a ticket becomes urgent.'),
                __('Integrations'),
                ['trigger' => $urgent(), 'steps' => [
                    $condition('ticket.priority', 'changed_to', 'urgent'),
                    $action('webhook', 'send_webhook', [
                        'method' => 'POST',
                        'url' => 'https://example.com/hooks/urgent-tickets',
                        'headers' => [['name' => 'Content-Type', 'value' => 'application/json']],
                        'body' => '{"number": "{{ ticket.number }}", "subject": "{{ ticket.subject }}"}',
                    ]),
                ]],
            ],
        };
    }
}
