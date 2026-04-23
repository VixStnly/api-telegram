<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AutoReplyController;
use App\Http\Controllers\Api\AutoReplyLogController;
use App\Http\Controllers\Api\AutoReplyRuleController;
use App\Http\Controllers\Api\ManagedDeviceController;

Route::prefix('v1')->group(function () {
    Route::post('/auto-reply/process', [AutoReplyController::class, 'process']);

    Route::get('/managed-devices', [ManagedDeviceController::class, 'index']);
    Route::post('/managed-devices', [ManagedDeviceController::class, 'store']);
    Route::get('/managed-devices/{id}', [ManagedDeviceController::class, 'show']);
    Route::put('/managed-devices/{id}', [ManagedDeviceController::class, 'update']);
    Route::delete('/managed-devices/{id}', [ManagedDeviceController::class, 'destroy']);

    Route::get('/auto-reply-rules', [AutoReplyRuleController::class, 'index']);
    Route::post('/auto-reply-rules', [AutoReplyRuleController::class, 'store']);
    Route::get('/auto-reply-rules/{id}', [AutoReplyRuleController::class, 'show']);
    Route::put('/auto-reply-rules/{id}', [AutoReplyRuleController::class, 'update']);
    Route::delete('/auto-reply-rules/{id}', [AutoReplyRuleController::class, 'destroy']);

    Route::get('/auto-reply-logs', [AutoReplyLogController::class, 'index']);
    Route::get('/auto-reply-logs/{id}', [AutoReplyLogController::class, 'show']);
});