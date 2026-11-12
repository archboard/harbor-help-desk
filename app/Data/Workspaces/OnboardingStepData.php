<?php

namespace App\Data\Workspaces;

use Spatie\LaravelData\Data;

/**
 * One item of the "Get set up" checklist. The key is stable; the label lives in the frontend.
 */
class OnboardingStepData extends Data
{
    public function __construct(
        public string $key,
        public bool $complete,
    ) {}
}
