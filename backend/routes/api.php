<?php

use App\Http\Controllers\Api\AlertChannelController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckerController;
use App\Http\Controllers\Api\MonitorController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::post('/check', [CheckerController::class, 'check'])->middleware('throttle:checker');

Route::post('/paddle/webhook', \App\Http\Controllers\PaddleWebhookController::class);

Route::get('/status/{slug}', [\App\Http\Controllers\Api\StatusPageController::class, 'show'])
    ->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/monitors', [MonitorController::class, 'index']);
    Route::post('/monitors', [MonitorController::class, 'store']);
    Route::get('/monitors/{monitor}', [MonitorController::class, 'show']);
    Route::put('/monitors/{monitor}', [MonitorController::class, 'update']);
    Route::delete('/monitors/{monitor}', [MonitorController::class, 'destroy']);
    Route::post('/monitors/{monitor}/pause', [MonitorController::class, 'pause']);
    Route::post('/monitors/{monitor}/resume', [MonitorController::class, 'resume']);

    Route::get('/alert-channels', [AlertChannelController::class, 'index']);
    Route::post('/alert-channels', [AlertChannelController::class, 'store']);
    Route::delete('/alert-channels/{alertChannel}', [AlertChannelController::class, 'destroy']);

    Route::get('/alerts', [AlertController::class, 'index']);

    Route::get('/billing/config', [\App\Http\Controllers\Api\BillingController::class, 'config']);
    Route::post('/billing/portal', [\App\Http\Controllers\Api\BillingController::class, 'portal']);
});
