<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete expired verification codes and refresh tokens (models using Prunable).
Schedule::command('model:prune')->daily();

// Delete Sanctum access tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
