<?php

namespace Tests\Fakes\Automations;

use App\Automations\Context;
use RuntimeException;

class CrashAction extends FakeAction
{
    public function key(): string
    {
        return 'fake.crash';
    }

    public function execute(Context $context, array $config): string
    {
        throw new RuntimeException('Boom');
    }
}
