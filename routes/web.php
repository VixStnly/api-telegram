<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagedDeviceWebController;
use App\Http\Controllers\AutoReplyRuleWebController;
use App\Http\Controllers\AutoReplyLogWebController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AutoReplyTestWebController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TelegramLoginController;
use App\Http\Controllers\TelegramUserWebController;
use App\Http\Controllers\TelegramAccessCodeWebController;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\TelegramAccessCode;

Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

Route::get('/telegram-login/{account}', [TelegramLoginController::class, 'show'])
    ->name('telegram-login.show');
Route::post('/telegram-login/{account}', [TelegramLoginController::class, 'store'])
    ->name('telegram-login.store');
    
Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('managed-devices', ManagedDeviceWebController::class);
    Route::resource('auto-reply-rules', AutoReplyRuleWebController::class);
    Route::resource('auto-reply-logs', AutoReplyLogWebController::class)->only(['index', 'show']);
    Route::get('/telegram-users', [TelegramUserWebController::class, 'index'])->name('telegram-users.index');
    Route::get('/telegram-users/{botChatId}', [TelegramUserWebController::class, 'show'])->name('telegram-users.show');
    Route::resource('telegram-access-codes', TelegramAccessCodeWebController::class)->except(['show']);

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
            'access_code_count' => TelegramAccessCode::count(),
            'latest_access_codes' => TelegramAccessCode::query()
                ->latest()
                ->take(5)
                ->get(['code', 'is_active', 'max_uses', 'used_count', 'expires_at', 'created_at'])
                ->map(function (TelegramAccessCode $code) {
                    return [
                        'code' => $code->code,
                        'is_active' => $code->is_active,
                        'max_uses' => $code->max_uses,
                        'used_count' => $code->used_count,
                        'expires_at' => $code->expires_at?->toISOString(),
                        'created_at' => $code->created_at?->toISOString(),
                        'available' => $code->isAvailable(),
                    ];
                }),
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
