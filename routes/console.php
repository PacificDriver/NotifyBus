<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('queue:work redis --sleep=3 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();


