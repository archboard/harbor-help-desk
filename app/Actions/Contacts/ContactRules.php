<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rules for a contact. Emails are unique in the workspace ignoring case, matching
 * the `contacts_workspace_id_email_unique` index on `lower(email)`.
 */
class ContactRules
{
    /**
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public static function for(?Contact $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
                    $taken = Contact::query()
                        ->whereRaw('lower(email) = lower(?)', [$value])
                        ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
                        ->exists();

                    if ($taken) {
                        $fail(__('A contact with this email already exists.'));
                    }
                },
            ],
        ];
    }
}
