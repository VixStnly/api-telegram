<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagedDeviceWebController;
use App\Http\Controllers\AutoReplyRuleWebController;
use App\Http\Controllers\AutoReplyLogWebController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutoReplyTestWebController;
use App\Http\Controllers\TelegramWebhookController;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');
Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('managed-devices', ManagedDeviceWebController::class);
    Route::resource('auto-reply-rules', AutoReplyRuleWebController::class);
    Route::resource('auto-reply-logs', AutoReplyLogWebController::class)->only(['index', 'show']);

    Route::get('/auto-reply-test', [AutoReplyTestWebController::class, 'index'])->name('auto-reply-test.index');
Route::post('/auto-reply-test', [AutoReplyTestWebController::class, 'process'])->name('auto-reply-test.process');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/debug-login', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'db_connected' => true,
            'database_name' => DB::connection()->getDatabaseName(),
            'users_table_count' => User::count(),
            'admin_exists' => User::where('email', 'admin@gmail.com')->exists(),
            'session_driver' => config('session.driver'),
            'app_key_exists' => !empty(config('app.key')),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'db_connected' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
require __DIR__.'/auth.php';