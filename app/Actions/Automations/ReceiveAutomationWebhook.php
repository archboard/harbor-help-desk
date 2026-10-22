<?php

namespace App\Actions\Automations;

use App\Actions\Tickets\CreateTicket;
use App\Automations\Context;
use App\Automations\RunCausation;
use App\Data\Automations\ReceiveAutomationWebhookData;
use App\Enums\AutomationRunStatus;
use App\Enums\TicketSource;
use App\Jobs\RunWebhookAutomation;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReceiveAutomationWebhook
{
    public const int MAX_BODY_BYTES = 65536;

    public const int MAX_PER_MINUTE = 60;

    public function __construct(protected StartAutomationRun $startRun, protected CreateTicket $createTicket, protected RunCausation $causation) {}

    /**
     * Handle a post to an automation's secret URL and queue the run. Landlord code: the request
     * has no workspace, so the automation is looked up across workspaces and its workspace is made
     * current for the rest of the call, then put back so it can't leak into later requests of the process.
     *
     * In order: the token (an unknown or disabled automation, a different trigger, a missing or
     * wrong token are all the same 404), the per-automation throttle (429), the body size (413) and
     * shape (422), then the ticket per the trigger config.
     *
     * `find` starts the run through {@see StartAutomationRun} (depth 0, ticket rate limit). `create`
     * has no ticket yet, so the run is created first without one and the ticket is opened under
     * {@see RunCausation} in the same transaction. Its `created` event then carries the run: the
     * automation can never trigger on it, and other automations triggered by it are one level deeper.
     *
     * @throws HttpException
     * @throws ValidationException
     */
    public function handle(string $automationId, string $token, string $body): AutomationRun
    {
        // Landlord code: the secret in the URL is the only credential, so the workspace scope can't apply yet.
        $automation = Automation::withoutGlobalScope('workspace')->with('workspace')->whereKey($automationId)->first();

        $tokenMatches = hash_equals($automation === null ? str_repeat('0', 64) : (string) $automation->webhook_token_hash, hash('sha256', $token));

        abort_unless($automation !== null && $tokenMatches && $automation->webhook_token_hash !== null && $automation->enabled && $automation->trigger_type === 'webhook', 404);

        $previous = Workspace::current();
        $automation->workspace->makeCurrent();

        try {
            return $this->receive($automation, $body);
        } finally {
            $previous === null ? Workspace::forgetCurrent() : $previous->makeCurrent();
        }
    }

    protected function receive(Automation $automation, string $body): AutomationRun
    {
        $throttleKey = "automation-webhook:{$automation->getKey()}";

        if (! RateLimiter::attempt($throttleKey, self::MAX_PER_MINUTE, fn () => true, 60)) {
            throw new HttpException(429, __('Too many requests.'), null, ['Retry-After' => RateLimiter::availableIn($throttleKey)]);
        }

        abort_if(strlen($body) > self::MAX_BODY_BYTES, 413, __('The payload is larger than 64 KB.'));

        $decoded = json_decode($body, true);
        $payload = ReceiveAutomationWebhookData::validateAndCreate(['payload' => $decoded])->payload;
        $config = $automation->definition->trigger->config;

        if ($config['mode'] === 'create') {
            $run = $this->createTicketAndRun($automation, $config, $payload);
        } else {
            $run = $this->startRun->handle($automation, new Context($this->find($automation, $config, $payload), payload: $payload));
        }

        if ($run->status === AutomationRunStatus::RUNNING) {
            RunWebhookAutomation::dispatch($run);
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     */
    protected function find(Automation $automation, array $config, array $payload): Ticket
    {
        $value = data_get($payload, $config['path']);
        $value = is_scalar($value) ? (string) $value : null;

        if ($config['match_by'] === 'number') {
            $value = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => PHP_INT_MAX]]) === false ? null : $value;
        }

        $ticket = $value === null ? null : Ticket::query()
            ->where($config['match_by'] === 'number' ? 'number' : 'id', $value)
            ->when($automation->desk_id, fn ($query, string $deskId) => $query->where('desk_id', $deskId))
            ->first();

        return $ticket ?? throw ValidationException::withMessages(['payload' => __('No ticket found for the value at ":path".', ['path' => $config['path']])]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $payload
     */
    protected function createTicketAndRun(Automation $automation, array $config, array $payload): AutomationRun
    {
        $desk = Desk::query()->whereNull('archived_at')->whereKey($config['desk_id'])->first()
            ?? throw ValidationException::withMessages(['payload' => __('The automation’s desk no longer exists or is archived.')]);

        $text = fn (?string $path): ?string => $path !== null && is_scalar($value = data_get($payload, $path)) ? (string) $value : null;
        $email = $text($config['requester_email_path']);

        $input = [
            'subject' => $text($config['subject_path']),
            'body' => $text($config['body_path']),
            'requester' => ['email' => $email, 'name' => $text($config['requester_name_path'] ?? null) ?? $email],
        ];

        return DB::transaction(function () use ($automation, $desk, $input, $payload): AutomationRun {
            $run = AutomationRun::query()->create([
                'workspace_id' => $automation->workspace_id,
                'automation_id' => $automation->getKey(),
                'depth' => 0,
                'status' => AutomationRunStatus::RUNNING,
                'definition' => $automation->definition,
                'log' => [],
                'state' => ['payload' => $payload],
            ]);

            $ticket = $this->causation->during($run->getKey(), fn (): Ticket => $this->createTicket->handle($automation, $desk, $input, TicketSource::WEBHOOK));

            $run->update(['ticket_id' => $ticket->getKey()]);

            return $run;
        });
    }
}
