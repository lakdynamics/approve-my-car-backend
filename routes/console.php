<?php

use App\Enums\UserType;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Make an existing account an admin. The API can't create admins, so this is how the first one is made.
Artisan::command('user:make-admin {email}', function (TokenService $tokens) {
    $user = User::where('email', Str::lower($this->argument('email')))->first();

    if ($user === null) {
        $this->error('No user with that email. They need to sign up first.');
        return 1;
    }

    DB::transaction(function () use ($user, $tokens) {
        $user->type = UserType::Admin;
        $user->save();

        // Sign them out so their next login returns the admin type.
        $tokens->revokeAllFor($user);
    });

    $this->info("{$user->email} is now an admin.");

    return 0;
})->purpose('Make an existing user an admin');

// Delete expired verification codes and refresh tokens (models using Prunable).
Schedule::command('model:prune')->daily();

// Delete Sanctum access tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
