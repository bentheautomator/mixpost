<?php

namespace Inovector\Mixpost;

use Illuminate\Console\Scheduling\Schedule as LaravelSchedule;

class Schedule
{
    public static function register(LaravelSchedule $schedule): void
    {
        // withoutOverlapping() prevents a slow run (many due posts) from piling up on
        // the next minute's tick; due posts simply roll into the following run.
        $schedule->command('mixpost:run-scheduled-posts')->everyMinute()->withoutOverlapping();
        $schedule->command('mixpost:import-account-data')->everyTwoHours()->withoutOverlapping();
        $schedule->command('mixpost:import-account-audience')->everyThreeHours()->withoutOverlapping();
        $schedule->command('mixpost:process-metrics')->everyThreeHours()->withoutOverlapping();
        $schedule->command('mixpost:delete-old-data')->daily();
        $schedule->command('mixpost:prune-temporary-directory')->hourly()->withoutOverlapping();
        $schedule->command('mixpost:refresh-tokens')->daily();
    }
}
