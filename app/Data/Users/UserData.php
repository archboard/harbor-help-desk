<?php

namespace App\Data\Users;

use Spatie\LaravelData\Data;

/**
 * An agent as other people see them.
 */
class UserData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
}
