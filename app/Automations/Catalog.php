<?php

namespace App\Automations;

use App\Automations\Fields\Field;
use App\Data\Automations\AutomationCatalogData;
use App\Data\Automations\CatalogActionData;
use App\Data\Automations\CatalogFieldData;
use App\Data\Automations\CatalogFieldGroupData;
use App\Data\Automations\CatalogOperatorData;
use App\Data\Automations\CatalogOptionData;
use App\Data\Automations\CatalogPlaceholderData;
use App\Data\Automations\CatalogTriggerData;
use App\Enums\DeskRole;
use App\Models\Desk;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Builds what the automation builder renders from, for one viewer: the registry plus the current
 * workspace's choices. Workspace admins see every desk and member; a desk lead only the desks
 * they lead and those desks' members. Built per request, so nothing tenant-specific is kept.
 */
class Catalog
{
    public function __construct(protected AutomationRegistry $registry) {}

    public function build(User $viewer): AutomationCatalogData
    {
        $desks = $this->options($this->desks($viewer)->pluck('name', 'id'));
        $users = $this->options($this->users($viewer)->pluck('name', 'id'));
        $tags = $this->options(Tag::query()->orderBy('name')->pluck('name', 'name'));

        $fields = collect($this->registry->fields())->groupBy(fn (Field $field): string => $field->group()->value)
            ->map(fn (Collection $group): array => $group->map(fn (Field $field): CatalogFieldData => new CatalogFieldData(
                $field->key(),
                $field->label(),
                $field->type(),
                match (true) {
                    $field->type() === FieldType::DESK => $desks,
                    in_array($field->type(), [FieldType::USER, FieldType::USERS], true) => $users,
                    $field->key() === 'ticket.tags' => $tags,
                    default => $this->options($field->options()),
                },
            ))->values()->all());

        return new AutomationCatalogData(
            triggers: array_values(array_map(fn ($trigger): CatalogTriggerData => new CatalogTriggerData(
                $trigger->key(), $trigger->label(), $trigger->fieldGroups(), $this->rules($trigger->configRules()),
                array_map(
                    fn (string $key, string $label): CatalogPlaceholderData => new CatalogPlaceholderData($key, $label),
                    array_keys($trigger->placeholders()),
                    $trigger->placeholders(),
                ),
            ), $this->registry->triggers())),
            fieldGroups: array_map(fn (FieldGroup $group): CatalogFieldGroupData => new CatalogFieldGroupData(
                $group, $group->label(), $fields->get($group->value, []),
            ), FieldGroup::cases()),
            operators: $this->operators(),
            actions: array_values(array_map(fn ($action): CatalogActionData => new CatalogActionData(
                $action->key(), $action->label(), str_replace('\\', '.', $action->configData()), $this->rules($action->configRules()),
            ), $this->registry->actions())),
            placeholders: array_map(
                fn (string $key, string $label): CatalogPlaceholderData => new CatalogPlaceholderData($key, $label),
                array_keys(PlaceholderRenderer::labels()),
                PlaceholderRenderer::labels(),
            ),
        );
    }

    /**
     * @return array<string, array<int, CatalogOperatorData>>
     */
    protected function operators(): array
    {
        $operators = [];

        foreach (FieldType::cases() as $type) {
            $operators[$type->value] = collect(Operator::cases())
                ->filter(fn (Operator $operator): bool => $operator->supports($type))
                ->map(fn (Operator $operator): CatalogOperatorData => new CatalogOperatorData($operator, $operator->label(), $operator->takesValue()))
                ->values()->all();
        }

        return $operators;
    }

    /**
     * @return EloquentCollection<int, Desk>
     */
    protected function desks(User $viewer): EloquentCollection
    {
        return $this->isAdmin($viewer)
            ? Desk::query()->orderBy('name')->get()
            : $viewer->desks()->wherePivot('role', DeskRole::LEAD)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, User>
     */
    protected function users(User $viewer): Collection
    {
        if ($this->isAdmin($viewer)) {
            return Workspace::current()->users()->orderBy('name')->get();
        }

        return User::query()
            ->whereHas('desks', fn (Builder $query) => $query->whereIn('desks.id', $this->desks($viewer)->modelKeys()))
            ->orderBy('name')
            ->get();
    }

    protected function isAdmin(User $viewer): bool
    {
        return $viewer->workspaceRole(Workspace::current())?->isAdmin() === true;
    }

    /**
     * @param  Collection<string, string>|array<string, string>  $choices  value => label
     * @return array<int, CatalogOptionData>
     */
    protected function options(Collection|array $choices): array
    {
        return collect($choices)->map(fn (string $label, string|int $value): CatalogOptionData => new CatalogOptionData((string) $value, $label))->values()->all();
    }

    /**
     * Only plain string rules can be shown, rule objects are dropped.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, list<string>>
     */
    protected function rules(array $rules): array
    {
        return array_map(fn (mixed $rule): array => array_values(array_filter(
            Arr::wrap(is_string($rule) ? explode('|', $rule) : $rule),
            is_string(...),
        )), $rules);
    }
}
