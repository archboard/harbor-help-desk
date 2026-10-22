<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\SyncTicketTags;
use App\Automations\Context;
use App\Automations\TagsMode;
use App\Data\Automations\Actions\TagsConfigData;

/**
 * Adds, removes or replaces the ticket's tags, through {@see SyncTicketTags}.
 */
class Tags extends ConfiguredAction
{
    public function key(): string
    {
        return 'tags';
    }

    public function label(): string
    {
        return __('Change tags');
    }

    public function configData(): string
    {
        return TagsConfigData::class;
    }

    public function execute(Context $context, array $config): string
    {
        $config = TagsConfigData::from($config);
        $names = $this->resulting($context, $config);

        app(SyncTicketTags::class)->handle($this->actor($context), $context->ticket, ['names' => $names]);

        return __('Tags are now: :tags.', ['tags' => $names === [] ? __('none') : implode(', ', $names)]);
    }

    public function describe(Context $context, array $config): string
    {
        $names = $this->resulting($context, TagsConfigData::from($config));

        return __('Would set the tags to: :tags.', ['tags' => $names === [] ? __('none') : implode(', ', $names)]);
    }

    /**
     * The tag names the ticket has once the config is applied.
     *
     * @return list<string>
     */
    protected function resulting(Context $context, TagsConfigData $config): array
    {
        $lower = fn (array $names): array => array_map(mb_strtolower(...), $names);
        $current = $context->ticket->tags()->pluck('name')->all();

        return array_values(match ($config->mode) {
            TagsMode::REPLACE => $config->names,
            TagsMode::ADD => [...$current, ...array_filter($config->names, fn (string $name): bool => ! in_array(mb_strtolower($name), $lower($current), true))],
            TagsMode::REMOVE => array_filter($current, fn (string $name): bool => ! in_array(mb_strtolower($name), $lower($config->names), true)),
        });
    }
}
