<?php

namespace App\Data\Automations\Actions;

use App\Models\Workspace;
use Illuminate\Validation\Rule;

class SetSlaPolicyConfigData extends ActionConfigData
{
    /**
     * @param  string|null  $slaPolicyId  The per-ticket override, or null to fall back to the desk's policy.
     */
    public function __construct(
        public ?string $slaPolicyId,
    ) {}

    public static function rules(): array
    {
        return [
            'sla_policy_id' => ['present', 'nullable', 'string', Rule::exists('sla_policies', 'id')->where('workspace_id', Workspace::current()?->getKey())],
        ];
    }
}
