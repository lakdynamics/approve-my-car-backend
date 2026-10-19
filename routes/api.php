<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Each API version lives in its own file under routes/api/. Breaking
| changes go into a new version, so existing app releases keep working.
|
*/

Route::prefix('v1')->name('v1.')->group(base_path('routes/api/v1.php'));
