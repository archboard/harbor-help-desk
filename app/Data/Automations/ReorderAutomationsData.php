<?php

namespace App\Data\Automations;

use App\Data\InputData;
use App\Models\Workspace;
use Illuminate\Validation\Rule;

/**
 * The automations of one trigger type, in their new order.
 */
class ReorderAutomationsData extends InputData
{
    /**
     * @param  list<string>  $ids
     */
    public function __construct(
        public array $ids,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'distinct', Rule::exists('automations', 'id')->where('workspace_id', Workspace::current()?->getKey())],
        ];
    }
}
