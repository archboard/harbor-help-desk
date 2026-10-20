<?php

namespace App\Data\Desks;

use App\Actions\Desks\DeskSlugRules;
use App\Data\InputData;
use Spatie\LaravelData\Optional;

/**
 * Slug uniqueness depends on the desk being updated, so `UpdateDesk` checks it.
 */
class UpdateDeskData extends InputData
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $slug,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', ...DeskSlugRules::FORMAT],
        ];
    }
}
