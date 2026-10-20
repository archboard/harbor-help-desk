<?php

namespace App\Data\ApiTokens;

use App\Data\InputData;
use Spatie\LaravelData\Attributes\Validation\Max;

/**
 * Which workspaces are allowed depends on the user, so `CreatePersonalAccessToken` checks them.
 */
class CreatePersonalAccessTokenData extends InputData
{
    /**
     * @param  list<string>  $workspaces  Workspace ids the token can reach.
     */
    public function __construct(
        #[Max(255)]
        public string $name,
        public array $workspaces,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'workspaces' => ['required', 'array', 'min:1'],
            'workspaces.*' => ['string'],
        ];
    }
}
