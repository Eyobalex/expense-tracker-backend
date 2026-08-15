<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('budget:initialize')->hourly()->onOneServer()->withoutOverlapping();
Schedule::command('receipt:reconcile-storage')->daily()->onOneServer()->withoutOverlapping();
Schedule::command('receipt:cleanup-artifacts')->daily()->onOneServer()->withoutOverlapping();
Schedule::command('fx:refresh-rates')->dailyAt((string) config('fx.refresh_time'))->timezone('UTC')->onOneServer()->withoutOverlapping(10);
