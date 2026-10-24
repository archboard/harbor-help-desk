<?php

namespace App\Data\Portal;

use App\Models\Desk;
use Spatie\LaravelData\Data;

/**
 * A desk as a contact sees it when choosing where to open a ticket.
 */
class PortalDeskData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(Desk $desk): self
    {
        return new self(id: $desk->id, name: $desk->name);
    }
}
