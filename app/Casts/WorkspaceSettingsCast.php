<?php

namespace App\Casts;

use App\Data\Workspaces\WorkspaceSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores only the settings that differ from the defaults, so changing a default in code reaches
 * every workspace that kept it. Unknown stored keys are ignored on read and dropped on write.
 *
 * @implements CastsAttributes<WorkspaceSettings, WorkspaceSettings|array<string, mixed>|null>
 */
class WorkspaceSettingsCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): WorkspaceSettings
    {
        $stored = json_decode($value ?? '{}', true) ?: [];

        return WorkspaceSettings::from(array_intersect_key($stored, (new WorkspaceSettings)->toArray()));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $defaults = (new WorkspaceSettings)->toArray();
        $settings = $value instanceof WorkspaceSettings ? $value : WorkspaceSettings::from($value ?? []);

        $overrides = array_filter(
            $settings->toArray(),
            fn (mixed $setting, string $name): bool => $setting !== $defaults[$name],
            ARRAY_FILTER_USE_BOTH,
        );

        return json_encode((object) $overrides);
    }
}
