<?php

namespace App\Console\Commands;

use App\Actions\Tickets\ApplyRetention;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tickets:apply-retention')]
#[Description('Trash old resolved tickets and purge old trashed ones, per each workspace\'s retention settings')]
class ApplyTicketRetention extends Command
{
    public function handle(ApplyRetention $applyRetention): void
    {
        Workspace::all()->eachCurrent(function () use ($applyRetention): void {
            $applyRetention->handle();
        });
    }
}
