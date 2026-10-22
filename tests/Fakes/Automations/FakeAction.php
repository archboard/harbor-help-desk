<?php

namespace Tests\Fakes\Automations;

use App\Automations\Actions\AutomationAction;
use App\Automations\Context;

/**
 * Base for the fake actions the automation tests register in place of the real ones.
 */
abstract class FakeAction implements AutomationAction
{
    public function label(): string
    {
        return $this->key();
    }

    public function configRules(): array
    {
        return [];
    }

    public function describe(Context $context, array $config): string
    {
        return "would {$this->key()}";
    }
}
