<?php

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => Workspace::current()?->getKey() ?? Workspace::factory(),
            'name' => fake()->unique()->words(2, true),
            'type' => CustomFieldType::TEXT,
        ];
    }

    /**
     * @param  list<string>  $options
     */
    public function select(array $options = ['Small', 'Medium', 'Large']): static
    {
        return $this->state(['type' => CustomFieldType::SELECT, 'options' => $options]);
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }
}
