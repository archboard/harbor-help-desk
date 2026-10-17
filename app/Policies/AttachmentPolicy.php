<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Anyone who can see a ticket can download its files. Uploaders can remove their own files;
 * removing someone else's takes the same rights as deleting the ticket.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('view', $attachment->ticket);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return ($attachment->isUploadedBy($user) && Gate::forUser($user)->allows('update', $attachment->ticket))
            || Gate::forUser($user)->allows('delete', $attachment->ticket);
    }
}
