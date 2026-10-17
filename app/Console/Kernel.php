<?php

namespace App\Console;

use App\Jobs\SyncBranchesRestaurantsJob;
use App\Jobs\SyncCustomersJob;
use App\Jobs\SyncEmployeesJob;
use App\Jobs\SyncFoodJob;
use App\Jobs\SyncOrdersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
        $schedule->job(new SyncOrdersJob)->everyMinute()->withoutOverlapping();
        $schedule->job(new SyncBranchesRestaurantsJob)->everyTwoHours();
        $schedule->job(new SyncEmployeesJob)->everyFourHours();
        $schedule->job(new SyncFoodJob)->hourly();
        $schedule->job(new SyncCustomersJob)->everyFourHours();
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
