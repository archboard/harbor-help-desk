<?php

namespace App\Data\Automations;

use App\Actions\Automations\ReceiveAutomationWebhook;
use App\Automations\DefinitionValidator;
use App\Data\InputData;
use App\Models\Workspace;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;

/**
 * An unsaved definition to dry-run against a ticket. `desk_id` is the scope it would be saved with.
 * That `ticket_event_id` belongs to the ticket is checked by the Action.
 */
class TestAutomationData extends InputData
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>|Optional|null  $samplePayload
     */
    public function __construct(
        #[LiteralTypeScriptType('App.Data.Automations.AutomationDefinitionData')] public array $definition,
        public string|Optional|null $deskId,
        public string $ticketId,
        public string|Optional|null $ticketEventId,
        public array|Optional|null $samplePayload,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $workspaceId = Workspace::current()?->getKey();

        return [
            'definition' => ['bail', 'required', 'array', app(DefinitionValidator::class)],
            'desk_id' => ['nullable', 'string', CreateAutomationData::deskExists()],
            'ticket_id' => ['required', 'string', Rule::exists('tickets', 'id')->where('workspace_id', $workspaceId)->whereNull('deleted_at')],
            'ticket_event_id' => ['nullable', 'string', Rule::exists('ticket_events', 'id')->where('workspace_id', $workspaceId)],
            'sample_payload' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen((string) json_encode($value)) > ReceiveAutomationWebhook::MAX_BODY_BYTES) {
                    $fail(__('The sample payload is larger than 64 KB.'));
                }
            }],
        ];
    }
}
