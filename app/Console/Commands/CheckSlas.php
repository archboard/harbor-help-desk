<?php

namespace App\Console\Commands;

use App\Actions\Tickets\CheckSlaTargets;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('slas:check')]
#[Description('Warn about and breach every workspace\'s SLA targets that ran out')]
class CheckSlas extends Command
{
    public function handle(CheckSlaTargets $checkSlaTargets): void
    {
        Workspace::all()->eachCurrent(function () use ($checkSlaTargets): void {
            // ponytail: one batch per workspace per run; loop until it returns 0 if a backlog builds up.
            $checkSlaTargets->handle();
        });
    }
}
