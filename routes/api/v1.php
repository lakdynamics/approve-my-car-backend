<?php
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::get('health', fn () => response()->json(['status' => 'ok']))->name('health');
Route::prefix('auth')->name('auth.')->group(function(){
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:auth-register')
        ->name('register');

    Route::post('register/verify', [RegisterController::class, 'verify'])
        ->middleware('throttle:auth-verify')
        ->name('register.verify');

    Route::post('register/resend', [RegisterController::class, 'resend'])
        ->middleware('throttle:auth-register')
        ->name('register.resend');

    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:auth-login')
        ->name('login');

    Route::post('refresh', [RefreshTokenController::class, 'store'])
        ->middleware('throttle:auth-refresh')
        ->name('refresh');

    Route::post('logout', [LoginController::class, 'destroy'])
        ->middleware('auth:sanctum')
        ->name('logout');
});

Route::middleware('auth:sanctum')->group(function(){
    Route::get('me', MeController::class)->name('me');
});
