<?php

namespace App\Http\Attributes;

use App\Data\InputData;
use Attribute;

/**
 * The input Data class whose properties are the endpoint's query string.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiQuery
{
    /**
     * @param  class-string<InputData>  $class
     */
    public function __construct(public string $class) {}
}
