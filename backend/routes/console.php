<?php

use Illuminate\Support\Facades\Schedule;

// Demo servers wipe and re-seed every night (run by the "scheduler" container).
Schedule::command('aviate:reset-demo', ['--force'])
    ->dailyAt(config('aviate.demo.reset_at'))
    ->timezone(config('aviate.demo.time_zone'))
    ->when(fn () => (bool) config('aviate.demo.enabled'))
    ->withoutOverlapping();
