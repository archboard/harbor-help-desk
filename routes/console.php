<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tickets:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('mailboxes:poll')->everyMinute()->withoutOverlapping();
Schedule::command('mailboxes:renew-push')->daily()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('model:prune')->daily();
