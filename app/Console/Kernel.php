<?php

namespace App\Console;

use App\Models\BusinessSetting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\SyncOrdersJob;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->job(new SyncOrdersJob)->everyFiveMinutes();
        $schedule->job(new SyncFoodJob)->everyFiveMinutes();
        $schedule->job(new SyncEmployeesJob)->everyFiveMinutes();

        // Auto print orders every 10 seconds if enabled
        if (config('printing.auto_print.enabled', false)) {
            $schedule->command('orders:auto-print')
                ->everyTenSeconds()
                ->withoutOverlapping()
                ->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
