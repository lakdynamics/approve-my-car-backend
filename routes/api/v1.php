<?php
use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\ChecklistController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\InspectorController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Admin\VehicleMakeController as AdminVehicleMakeController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\RefreshTokenController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\Inspector\BookingController as InspectorBookingController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\VehicleController;
use App\Http\Controllers\Api\V1\VehicleMakeController;
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
    Route::patch('me', [ProfileController::class, 'update'])->name('me.update');
    Route::put('me/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:account-password')
        ->name('me.password');
    Route::delete('me', [ProfileController::class, 'destroy'])
        ->middleware('throttle:account-password')
        ->name('me.destroy');

    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicle-makes', VehicleMakeController::class)->name('vehicle-makes.index');

    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('bookings', [BookingController::class, 'store'])
        ->middleware('throttle:bookings-create')
        ->name('bookings.store');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
});

Route::prefix('inspector')->name('inspector.')->middleware(['auth:sanctum', 'can:inspector'])->group(function () {
    Route::get('bookings', [InspectorBookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [InspectorBookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{booking}/accept', [InspectorBookingController::class, 'accept'])->name('bookings.accept');
    Route::post('bookings/{booking}/start', [InspectorBookingController::class, 'start'])->name('bookings.start');
    Route::post('bookings/{booking}/complete', [InspectorBookingController::class, 'complete'])->name('bookings.complete');
});

Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'can:admin'])->group(function(){
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('inspectors', [InspectorController::class, 'index'])->name('inspectors.index');
    Route::post('inspectors', [InspectorController::class, 'store'])->name('inspectors.store');
    Route::put('inspectors/{user}', [InspectorController::class, 'update'])->name('inspectors.update');
    Route::delete('inspectors/{user}', [InspectorController::class, 'destroy'])->name('inspectors.destroy');
    Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
    Route::get('vehicle-makes', [AdminVehicleMakeController::class, 'index'])->name('vehicle-makes.index');
    Route::post('vehicle-makes', [AdminVehicleMakeController::class, 'store'])->name('vehicle-makes.store');
    Route::patch('vehicle-makes/{vehicleMake}', [AdminVehicleMakeController::class, 'update'])->name('vehicle-makes.update');

    Route::prefix('checklist')->name('checklist.')->group(function () {
        Route::get('/', [ChecklistController::class, 'index'])->name('index');
        Route::post('sections', [ChecklistController::class, 'storeSection'])->name('sections.store');
        Route::put('sections/order', [ChecklistController::class, 'reorderSections'])->name('sections.order');
        Route::patch('sections/{section}', [ChecklistController::class, 'updateSection'])->name('sections.update');
        Route::post('sections/{section}/items', [ChecklistController::class, 'storeItem'])->name('items.store');
        Route::put('sections/{section}/items/order', [ChecklistController::class, 'reorderItems'])->name('items.order');
        Route::patch('items/{item}', [ChecklistController::class, 'updateItem'])->name('items.update');
    });
});
