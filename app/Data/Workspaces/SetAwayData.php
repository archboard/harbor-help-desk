<?php

namespace App\Data\Workspaces;

use App\Data\InputData;

class SetAwayData extends InputData
{
    public function __construct(
        public bool $away,
    ) {}
}
