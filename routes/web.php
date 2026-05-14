<?php

use Illuminate\Support\Facades\Route;
use Peanutgraphic\BloxyTesterBridge\Http\Controllers\ClockController;
use Peanutgraphic\BloxyTesterBridge\Http\Controllers\HealthController;
use Peanutgraphic\BloxyTesterBridge\Http\Middleware\VerifyTesterRequest;

Route::middleware(VerifyTesterRequest::class)->prefix('tester')->group(function () {
    Route::get('/health', HealthController::class)->name('tester.health');
    Route::post('/clock/advance', [ClockController::class, 'advance'])->name('tester.clock.advance');
});
