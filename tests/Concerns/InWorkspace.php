<?php

namespace Tests\Concerns;

use App\Models\Workspace;
use Illuminate\Support\Facades\URL;

/**
 * Makes a fresh workspace current before each test, for tests of tenant routes, and builds
 * URLs on its host, since requests find their workspace by host.
 */
trait InWorkspace
{
    protected Workspace $workspace;

    protected function setUpInWorkspace(): void
    {
        $this->workspace = Workspace::factory()->create()->makeCurrent();

        URL::forceRootUrl(rtrim($this->workspace->url(), '/'));
    }
}
