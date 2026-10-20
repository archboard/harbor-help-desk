<?php

namespace App\Data\Contacts;

use App\Models\Contact;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;

class ContactData extends Data
{
    /**
     * @param  int|Lazy  $ticketsCount  Tickets the viewer can see, when counted.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public int|Lazy $ticketsCount,
    ) {}

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->id,
            name: $contact->name,
            email: $contact->email,
            ticketsCount: Lazy::when(
                fn (): bool => array_key_exists('tickets_count', $contact->getAttributes()),
                fn (): int => $contact->tickets_count,
            ),
        );
    }
}
