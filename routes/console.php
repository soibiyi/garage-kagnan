<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Notifications\AdminInstructionReceived;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    DB::table('notifications')
        ->where('type', AdminInstructionReceived::class)
        ->whereNotNull('read_at')
        ->where('read_at', '<=', now()->subHours(72))
        ->delete();
})->hourly();
