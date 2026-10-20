<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Closure;

/**
 * Emails are unique in the workspace ignoring case, matching the
 * `contacts_workspace_id_email_unique` index on `lower(email)`.
 */
class ContactRules
{
    public static function uniqueEmail(?Contact $ignore = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            $taken = Contact::query()
                ->whereRaw('lower(email) = lower(?)', [$value])
                ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('A contact with this email already exists.'));
            }
        };
    }
}
