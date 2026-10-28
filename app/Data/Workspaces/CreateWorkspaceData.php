<?php

namespace App\Data\Workspaces;

use App\Actions\Workspaces\SubdomainRules;
use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Max;

class CreateWorkspaceData extends InputData
{
    public function __construct(
        #[Max(255)]
        public string $name,
        public string $subdomain,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['subdomain' => ['required', ...SubdomainRules::FORMAT, SubdomainRules::unique()]];
    }
}
