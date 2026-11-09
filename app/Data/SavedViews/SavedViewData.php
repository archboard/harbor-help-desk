<?php

namespace App\Data\SavedViews;

use App\Data\Users\UserData;
use App\Models\SavedView;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use stdClass;

class SavedViewData extends Data
{
    /**
     * @param  stdClass  $filters  `SearchTickets` filters, as an object even when empty so clients
     *                             always get a map of filter => value. The API may store `deleted`
     *                             as a boolean.
     */
    public function __construct(
        public string $id,
        public string $userId,
        public UserData|Lazy $owner,
        public string $name,
        #[LiteralTypeScriptType('Record<string, string | string[] | boolean | Record<string, string | string[] | boolean>>')]
        public stdClass $filters,
        public bool $isShared,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(SavedView $view): self
    {
        return new self(
            id: $view->id,
            userId: $view->user_id,
            owner: Lazy::whenLoaded('user', $view, fn (): UserData => UserData::from($view->user)),
            name: $view->name,
            filters: (object) $view->filters,
            isShared: $view->is_shared,
            createdAt: $view->created_at,
            updatedAt: $view->updated_at,
        );
    }
}
