<?php

namespace App\Automations\Triggers;

use App\Actions\Automations\ReceiveAutomationWebhook;
use App\Automations\Context;
use App\Automations\EditorScope;
use App\Automations\FieldGroup;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketEvent;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * A JSON payload posted to the automation's secret URL, handled by {@see ReceiveAutomationWebhook}.
 * `find` runs on the ticket whose number or id sits at `path` in the payload; `create` opens a ticket
 * on `desk_id` from the payload paths (`*_path` values are dot paths into the payload).
 * It never matches a ticket event. The payload is read as `webhook.payload.<path>` fields and placeholders.
 */
class Webhook implements Trigger
{
    /** How many payload values are offered as placeholders. */
    public const int MAX_PLACEHOLDERS = 200;

    private const string PATH = 'regex:/^[\w.-]+$/';

    public function key(): string
    {
        return 'webhook';
    }

    public function label(): string
    {
        return __('A webhook is received');
    }

    public function configRules(): array
    {
        return [
            'mode' => ['required', 'in:find,create'],
            'match_by' => ['required_if:mode,find', 'prohibited_unless:mode,find', 'in:number,id'],
            'path' => ['required_if:mode,find', 'prohibited_unless:mode,find', 'string', 'max:255', self::PATH],
            'desk_id' => ['required_if:mode,create', 'prohibited_unless:mode,create', 'string', $this->deskRule()],
            'subject_path' => ['required_if:mode,create', 'prohibited_unless:mode,create', 'string', 'max:255', self::PATH],
            'body_path' => ['required_if:mode,create', 'prohibited_unless:mode,create', 'string', 'max:255', self::PATH],
            'requester_email_path' => ['required_if:mode,create', 'prohibited_unless:mode,create', 'string', 'max:255', self::PATH],
            'requester_name_path' => ['nullable', 'prohibited_unless:mode,create', 'string', 'max:255', self::PATH],
        ];
    }

    public function fieldGroups(): array
    {
        return [FieldGroup::WEBHOOK];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        return false;
    }

    public function placeholders(): array
    {
        return ['webhook.payload.*' => __('A value of the webhook payload, e.g. webhook.payload.order.id')];
    }

    public function placeholderValues(Context $context): array
    {
        $values = [];

        foreach (Arr::dot($context->payload) as $path => $value) {
            if (is_scalar($value)) {
                $values["webhook.payload.$path"] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            }

            if (count($values) >= self::MAX_PLACEHOLDERS) {
                break;
            }
        }

        return $values;
    }

    /**
     * An active desk of the workspace that the editor may open tickets on, and the automation's own desk when it has one.
     * Evaluated at validation time, when {@see EditorScope} is set.
     */
    protected function deskRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $scope = app(EditorScope::class);
            $desk = is_string($value) ? Desk::query()->whereNull('archived_at')->find($value) : null;

            if ($desk === null) {
                $fail(__('This desk does not exist.'));
            } elseif ($scope->deskId !== null && $scope->deskId !== $desk->getKey()) {
                $fail(__('Tickets can only be created on the automation’s desk.'));
            } elseif (! Gate::forUser($scope->editor())->allows('create', [Ticket::class, $desk])) {
                $fail(__('You can not create tickets on this desk.'));
            }
        };
    }
}
