<?php

namespace Tests;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Send requests for the app's root host to the current workspace's subdomain, as a browser
     * would, since the workspace is resolved from the request alone. Explicit hosts are kept.
     */
    protected function prepareUrlForRequest($uri)
    {
        return $this->onWorkspaceHost(parent::prepareUrlForRequest($uri));
    }

    public function from(string $url)
    {
        return parent::from($this->onWorkspaceHost($url));
    }

    protected function onWorkspaceHost(string $url): string
    {
        $workspace = Workspace::current();
        $root = rtrim((string) config('app.url'), '/');

        if ($workspace === null || ! config('harbor.multitenant') || ! str_starts_with($url, $root)) {
            return $url;
        }

        return rtrim($workspace->url(substr($url, strlen($root))), '/');
    }
}
