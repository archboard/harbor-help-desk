<?php

namespace App\Actions\Desks;

use App\Data\Desks\CreateDeskData;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Desk;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateDesk
{
    /**
     * Create a desk in the current workspace. The slug defaults to one made from the name.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Desk
    {
        Gate::forUser($actor)->authorize('create', Desk::class);

        $input['slug'] ??= Str::slug((string) ($input['name'] ?? ''));

        $desk = Desk::create(CreateDeskData::validateAndCreate($input)->filled());

        AuditEvent::record(AuditAction::DESK_CREATED, $actor, $desk);

        return $desk;
    }
}
