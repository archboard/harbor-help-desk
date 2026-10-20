<?php

namespace App\Data\Workspaces;

use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Max;

class UpdateWorkspaceData extends InputData
{
    public function __construct(
        #[Max(255)]
        public string $name,
    ) {}
}
