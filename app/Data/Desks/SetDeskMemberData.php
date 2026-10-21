<?php

namespace App\Data\Desks;

use App\Data\InputData;
use App\Enums\DeskRole;
use Spatie\LaravelData\Optional;

class SetDeskMemberData extends InputData
{
    public function __construct(
        public DeskRole $role,
        public bool|Optional $inRotation,
    ) {}
}
