<?php

use App\Console\Commands\DispatchPendingRefunds;
use Illuminate\Support\Facades\Schedule;

Schedule::command(DispatchPendingRefunds::class)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer();
