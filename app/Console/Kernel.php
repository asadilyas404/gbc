<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\SyncOrdersJob;
use App\Jobs\SyncFoodJob;
use App\Jobs\SyncEmployeesJob;
use App\Jobs\SyncBranchesRestaurantsJob;
use App\Jobs\SyncCustomersJob;

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
        $schedule->job(new SyncOrdersJob)->everyMinute();
        $schedule->job(new SyncBranchesRestaurantsJob)->dailyAt('23:30');
        $schedule->job(new SyncEmployeesJob)->dailyAt('23:35');
        $schedule->job(new SyncFoodJob)->hourly();
        $schedule->job(new SyncCustomersJob)->dailyAt('23:45');
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
