<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('probes:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('rollups:compute')->hourlyAt(2);
Schedule::command('model:prune')->dailyAt('03:17');
