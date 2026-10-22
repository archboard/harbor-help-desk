<?php

namespace App\Automations\Fields;

use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Automations\FieldType;
use App\Models\BusinessSchedule;
use Closure;

/**
 * A field defined by a closure, for the many fields that differ only in what they read.
 */
class ClosureField implements Field
{
    /**
     * @param  Closure(Context, ?BusinessSchedule): mixed  $resolver
     * @param  (Closure(): array<string, string>)|null  $options
     */
    public function __construct(
        protected string $key,
        protected string $label,
        protected FieldGroup $group,
        protected FieldType $type,
        protected Closure $resolver,
        protected ?Closure $options = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function group(): FieldGroup
    {
        return $this->group;
    }

    public function type(): FieldType
    {
        return $this->type;
    }

    public function options(): array
    {
        return $this->options ? ($this->options)() : [];
    }

    public function resolve(Context $context, ?BusinessSchedule $schedule = null): mixed
    {
        return ($this->resolver)($context, $schedule);
    }
}
