<?php

namespace App\Actions\Automations;

use App\Data\Automations\ListAutomationRunsData;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class ListAutomationRuns
{
    public const int PER_PAGE = 25;

    /**
     * The automation's runs, or the runs of every automation the actor may view when none is given,
     * newest first, filtered by status, ticket and creation date.
     *
     * @param  array<string, mixed>  $input
     * @return LengthAwarePaginator<int, AutomationRun>
     */
    public function handle(User $actor, ?Automation $automation, array $input): LengthAwarePaginator
    {
        $automation === null
            ? Gate::forUser($actor)->authorize('viewAny', Automation::class)
            : Gate::forUser($actor)->authorize('view', $automation);

        $filters = ListAutomationRunsData::validateAndCreate($input);

        $from = $filters->from instanceof Optional ? null : CarbonImmutable::parse($filters->from);
        $to = $filters->to instanceof Optional ? null : CarbonImmutable::parse($filters->to)->endOfDay();

        return AutomationRun::query()
            ->when($automation === null,
                fn ($query) => $query->whereIn('automation_id', Automation::visibleTo($actor)->select('id')),
                fn ($query) => $query->where('automation_id', $automation->getKey()),
            )
            ->with(['automation', 'ticket'])
            ->when(! $filters->status instanceof Optional, fn ($query) => $query->where('status', $filters->status))
            ->when(! $filters->ticketId instanceof Optional, fn ($query) => $query->where('ticket_id', $filters->ticketId))
            ->when(! $filters->ticketNumber instanceof Optional, fn ($query) => $query->whereHas('ticket', fn ($ticket) => $ticket->where('number', $filters->ticketNumber)))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->latest()->latest('id')
            ->paginate(self::PER_PAGE);
    }
}
