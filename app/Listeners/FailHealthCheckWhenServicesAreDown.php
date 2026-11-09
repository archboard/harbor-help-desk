<?php

namespace App\Listeners;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Makes `/up` return 500 when Postgres or Redis is unreachable. Horizon and the scheduler
 * stay out of it, so the web container stays healthy when a worker dies.
 */
class FailHealthCheckWhenServicesAreDown
{
    public function handle(DiagnosingHealth $event): void
    {
        DB::connection()->getPdo();
        DB::select('select 1');
        Redis::connection()->ping();
    }
}
