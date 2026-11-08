<?php

namespace App\Http\Attributes;

use App\Data\InputData;
use Attribute;

/**
 * The JSON request body of an API endpoint: an input Data class, a literal schema, or neither
 * for a POST/PUT/PATCH endpoint that takes no body.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiBody
{
    /**
     * @param  class-string<InputData>|null  $class
     * @param  array<string, mixed>  $schema
     */
    public function __construct(public ?string $class = null, public array $schema = []) {}
}
