<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplyRetention
{
    public function __construct(private DeleteTicket $deleteTicket) {}

    /**
     * Apply the current workspace's retention settings: soft-delete tickets resolved more than the
     * configured days ago (by `ticket_metrics.resolved_at`, while still resolved), then force-delete
     * tickets trashed longer than the trash window, along with their attachment files.
     *
     * @return array{trashed: int, purged: int}
     */
    public function handle(): array
    {
        $settings = Workspace::current()->settings;
        $trashed = 0;
        $purged = 0;

        if ($settings->retentionResolvedTicketDays !== null) {
            $resolvedStatuses = collect(TicketStatus::cases())->filter->isResolved()->map->value->all();

            Ticket::query()
                ->whereIn('status', $resolvedStatuses)
                ->whereHas('metric', fn ($query) => $query->where('resolved_at', '<', now()->subDays($settings->retentionResolvedTicketDays)))
                ->lazyById()
                ->each(function (Ticket $ticket) use (&$trashed): void {
                    $this->deleteTicket->trash($ticket, null);
                    $trashed++;
                });
        }

        Ticket::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($settings->retentionTrashDays))
            ->lazyById()
            ->each(function (Ticket $ticket) use (&$purged): void {
                $files = DB::transaction(function () use ($ticket): array {
                    $files = Attachment::query()->where('ticket_id', $ticket->getKey())->get()
                        ->map(fn (Attachment $attachment): array => [$attachment->disk, $attachment->path])->all();
                    $ticket->forceDelete();

                    return $files;
                });

                foreach ($files as [$disk, $path]) {
                    Storage::disk($disk)->delete($path);
                }

                $purged++;
            });

        return ['trashed' => $trashed, 'purged' => $purged];
    }
}
