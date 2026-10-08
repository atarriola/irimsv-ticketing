<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Housekeeping that runs once a night: tickets nobody reopened are closed, old read
// notifications go, and tickets deleted long enough ago are removed for good.
Schedule::daily()
    ->onOneServer()
    ->withoutOverlapping()
    ->group(function (): void {
        Schedule::command('tickets:close-resolved');
        Schedule::command('notifications:prune');
        Schedule::command('model:prune');
    });
