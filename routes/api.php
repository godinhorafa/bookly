<?php

declare(strict_types=1);

use App\Http\Controllers\Api\TokenController;
use Illuminate\Support\Facades\Route;

Route::post('/tokens', [TokenController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [TokenController::class, 'me']);
});
