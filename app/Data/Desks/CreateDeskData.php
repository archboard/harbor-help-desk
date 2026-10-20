<?php

namespace App\Data\Desks;

use App\Actions\Desks\DeskSlugRules;
use App\Data\InputData;
use App\Models\Workspace;
use Spatie\LaravelData\Attributes\Validation\Max;

class CreateDeskData extends InputData
{
    public function __construct(
        #[Max(255)]
        public string $name,
        public string $slug,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['slug' => ['required', ...DeskSlugRules::FORMAT, DeskSlugRules::unique(Workspace::current())]];
    }
}
