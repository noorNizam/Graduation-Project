<?php

use App\Domain\Services\SchedulerLogServiceInterface;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$logCommand = function (string $jobName, string $signature) {
    return app(SchedulerLogServiceInterface::class)->runCommand($jobName, $signature);
};

Schedule::call(fn () => $logCommand('serving-requests:auto-complete', 'serving-requests:auto-complete'))->hourly();

Schedule::call(fn () => $logCommand('top-performers:calculate', 'top-performers:calculate'))->lastDayOfMonth('23:00');
