<?php

namespace App\Data\Palette;

use App\Data\Desks\DeskData;
use App\Data\Macros\MacroData;
use App\Data\Tags\TagData;
use App\Data\Users\UserData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * What the command palette needs to offer ticket actions: loaded on first open.
 */
class PaletteData extends Data
{
    /**
     * @param  array<string, string>  $statuses
     * @param  array<string, string>  $priorities
     * @param  Collection<int, UserData>  $agents
     * @param  Collection<int, MacroData>  $macros
     * @param  Collection<int, DeskData>  $desks
     * @param  Collection<int, TagData>  $tags
     */
    public function __construct(
        public array $statuses,
        public array $priorities,
        public Collection $agents,
        public Collection $macros,
        public Collection $desks,
        public Collection $tags,
    ) {}

    public static function forUser(User $user): self
    {
        return new self(
            statuses: TicketStatus::options(),
            priorities: TicketPriority::options(),
            agents: UserData::collect(Workspace::current()->users()->orderBy('name')->get()),
            macros: MacroData::collect(Macro::visibleTo($user)->orderBy('name')->get()),
            desks: DeskData::collect(Desk::visibleTo($user)->with('customFields')->orderBy('name')->get()),
            tags: TagData::collect(Tag::query()->orderBy('name')->get()),
        );
    }
}
