<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Roll the bookable window forward nightly so freshly-in-horizon dates appear.
Schedule::command('availabilities:generate')->dailyAt('03:00');
