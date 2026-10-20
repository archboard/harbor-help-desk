<?php

namespace App\Data\Users;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * The signed-in user, shared with every page.
 */
class CurrentUserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public ?CarbonImmutable $emailVerifiedAt,
        public ?string $timezone,
    ) {}
}
