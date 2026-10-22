<?php

namespace App\Automations;

use RuntimeException;

/**
 * A failure the automations code throws on purpose. Its message is safe to show in the run
 * history, unlike the message of an unexpected exception.
 */
class AutomationException extends RuntimeException {}
