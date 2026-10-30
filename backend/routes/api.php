<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\DriverRideController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\RideController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/cities', [CityController::class, 'index']);

    // Rider actions
    Route::middleware('role:rider')->group(function () {
        Route::post('/rides/estimate', [RideController::class, 'estimate']);
        Route::post('/rides', [RideController::class, 'store'])->middleware('idempotent');
        Route::get('/rides', [RideController::class, 'index']);
        Route::get('/rides/current', [RideController::class, 'current']);
        Route::post('/rides/{ride}/coming', [RideController::class, 'coming'])->middleware('idempotent');
    });

    // Riders and drivers, each limited to their own rides
    Route::get('/rides/{ride}', [RideController::class, 'show']);
    Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel'])->middleware('idempotent');

    // Driver actions
    Route::middleware('role:driver')->group(function () {
        Route::post('/rides/{ride}/accept', [DriverRideController::class, 'accept'])->middleware('idempotent');
        Route::post('/rides/{ride}/arrive', [DriverRideController::class, 'arrive'])->middleware('idempotent');
        Route::post('/rides/{ride}/start', [DriverRideController::class, 'start'])->middleware('idempotent');
        Route::post('/rides/{ride}/complete', [DriverRideController::class, 'complete'])->middleware('idempotent');
        Route::get('/driver/rides/available', [DriverRideController::class, 'available']);
        Route::get('/driver/rides/current', [DriverRideController::class, 'current']);
        Route::patch('/driver/status', [DriverRideController::class, 'updateStatus']);
        Route::patch('/driver/location', [DriverRideController::class, 'updateLocation']);
    });
});
