<?php

namespace App\Actions\Tickets;

use App\Enums\TicketEventType;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DeleteTicketAttachment
{
    /**
     * Remove the file for good. The audit trail keeps its name.
     */
    public function handle(User $actor, Attachment $attachment): void
    {
        Gate::forUser($actor)->authorize('delete', $attachment);

        DB::transaction(function () use ($actor, $attachment): void {
            $attachment->delete();
            $attachment->ticket->recordEvent(TicketEventType::ATTACHMENT_REMOVED, $actor, ['attachment_id' => $attachment->id, 'filename' => $attachment->filename]);
        });

        Storage::disk($attachment->disk)->delete($attachment->path);
    }
}
