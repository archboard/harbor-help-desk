<?php

namespace Tests\Fakes\Automations;

use App\Automations\Context;
use Illuminate\Validation\ValidationException;

/**
 * Rejects like an Action does when its input is invalid.
 */
class RejectAction extends FakeAction
{
    public function key(): string
    {
        return 'fake.reject';
    }

    public function execute(Context $context, array $config): string
    {
        throw ValidationException::withMessages(['status' => ['The status is invalid.'], 'name' => ['The name is required.']]);
    }
}
