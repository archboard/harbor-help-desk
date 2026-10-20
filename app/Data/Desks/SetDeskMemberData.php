<?php

namespace App\Data\Desks;

use App\Data\InputData;
use App\Enums\DeskRole;

class SetDeskMemberData extends InputData
{
    public function __construct(
        public DeskRole $role,
    ) {}
}
