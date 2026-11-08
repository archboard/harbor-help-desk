<?php

namespace App\Http\Attributes;

use Attribute;
use Spatie\LaravelData\Data;

/**
 * The success response of an endpoint whose return type can't say it: an output Data class
 * (optionally paginated, cursor paginated or a list), a literal schema, or neither for an empty body.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ApiResponse
{
    /**
     * @param  class-string<Data>|null  $class
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        public ?string $class = null,
        public bool $paginated = false,
        public bool $cursor = false,
        public bool $list = false,
        public int $status = 200,
        public array $schema = [],
    ) {}
}
