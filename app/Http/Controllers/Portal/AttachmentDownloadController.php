<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentDownloadController extends Controller
{
    /**
     * Download a file from a public comment on the contact's own ticket. Internal notes' files and files attached to the ticket alone stay agent-only.
     */
    public function __invoke(string $attachment): StreamedResponse
    {
        $ticketIds = Auth::guard('contact')->user()->tickets()->select('tickets.id');

        $attachment = Attachment::query()
            ->whereIn('ticket_id', $ticketIds)
            ->whereHas('comment', fn ($query) => $query->where('is_internal', false))
            ->findOrFail($attachment);

        return $attachment->download();
    }
}
