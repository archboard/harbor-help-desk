<?php

namespace Tests\Concerns;

use App\Models\Workspace;

/**
 * Makes a fresh workspace current before each test, for tests of tenant routes.
 */
trait InWorkspace
{
    protected Workspace $workspace;

    protected function setUpInWorkspace(): void
    {
        $this->workspace = Workspace::factory()->create()->makeCurrent();
    }
}
