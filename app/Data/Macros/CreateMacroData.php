<?php

namespace App\Data\Macros;

use App\Data\InputData;
use App\Enums\MacroScope;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Workspace;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * Needs a body, actions or both. `actions` is a fixed set of changes, never free-form.
 */
class CreateMacroData extends InputData
{
    /**
     * @param  array<string, mixed>|Optional  $actions
     */
    public function __construct(
        public string $name,
        public MacroScope $scope,
        public string|Optional $deskId,
        public string|Optional|null $body,
        public bool|Optional $bodyIsInternal,
        public array|Optional $actions,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        $isDesk = ($context->payload['scope'] ?? null) === MacroScope::DESK->value;

        return [
            'name' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::enum(MacroScope::class)],
            'desk_id' => $isDesk
                ? ['required', 'string', Rule::exists('desks', 'id')->where('workspace_id', Workspace::current()?->getKey())->whereNull('archived_at')]
                : ['prohibited'],
            'body' => ['nullable', 'string', 'required_without:actions'],
            'body_is_internal' => ['sometimes', 'boolean'],
            'actions' => ['array:status,priority,add_tags,remove_tags,assign_to_me', 'required_without:body'],
            ...self::actionRules(),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function actionRules(): array
    {
        return [
            'actions.status' => ['string', Rule::enum(TicketStatus::class)],
            'actions.priority' => ['string', Rule::enum(TicketPriority::class)],
            'actions.add_tags' => ['array'],
            'actions.add_tags.*' => ['string', 'max:255'],
            'actions.remove_tags' => ['array'],
            'actions.remove_tags.*' => ['string', 'max:255'],
            'actions.assign_to_me' => ['boolean'],
        ];
    }
}
