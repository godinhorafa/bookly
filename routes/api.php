<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SpaceController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Middleware\Idempotency;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [TokenController::class, 'me']);

    Route::get('/spaces', [SpaceController::class, 'index']);
    Route::get('/spaces/{space}/availability', [SpaceController::class, 'availability']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);

    Route::middleware(Idempotency::class)->group(function (): void {
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/bookings/{booking}/payment', [BookingController::class, 'pay']);
        Route::post('/bookings/{booking}/cancellation', [BookingController::class, 'cancel']);
    });
});
