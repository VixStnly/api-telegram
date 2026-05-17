<?php

namespace App\Http\Controllers;

use App\Models\TelegramClientAccount;
use App\Models\TelegramClientGroup;
use App\Models\TelegramAccessCode;
use App\Models\TelegramGroup;
use App\Services\AutoReplyEngine;
use App\Services\PyrogramWorkerService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public function handle(
        Request $request,
        TelegramBotService $telegram,
        AutoReplyEngine $engine,
        PyrogramWorkerService $pyrogram
    ) {
        $secret = config('services.telegram.webhook_secret');
        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret && $headerSecret !== $secret) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid secret token',
            ], 403);
        }

        try {
            $update = $request->all();
            $rawUpdate = json_decode($request->getContent(), true) ?: [];
            $callbackQuery = $update['callback_query'] ?? null;

            if ($callbackQuery) {
                $this->handleCallbackQuery($callbackQuery, $telegram, $pyrogram);

                return response()->json(['ok' => true]);
            }

            $message = $update['message'] ?? null;
            $rawMessage = $rawUpdate['message'] ?? [];

            if (! $message) {
                return response()->json(['ok' => true]);
            }

            $chat = $message['chat'] ?? [];
            $from = $message['from'] ?? [];
            $rawText = (string) ($rawMessage['text'] ?? $message['text'] ?? '');
            $text = trim($rawText);
            $chatId = (string) ($chat['id'] ?? '');

            if (in_array(($chat['type'] ?? ''), ['group', 'supergroup', 'channel'])) {
                TelegramGroup::updateOrCreate(
                    ['chat_id' => $chatId],
                    [
                        'title' => $chat['title'] ?? null,
                        'username' => $chat['username'] ?? null,
                        'type' => $chat['type'] ?? null,
                        'is_active' => true,
                        'is_allowed_for_broadcast' => true,
                        'last_seen_at' => now(),
                        'meta' => [
                            'raw_chat' => $chat,
                        ],
                    ]
                );
            }

            if ($text === '/start' && $chatId !== '') {
                $account = $this->registerClientAccount($chatId, $from);

                if (! in_array($account->auth_status, ['authorized', 'awaiting_access_code', 'sending_code', 'awaiting_code', 'awaiting_password'], true)) {
                    $account->update([
                        'auth_status' => 'idle',
                        'phone_code_hash' => null,
                        'pending_otp_code' => null,
                        'pending_session_string' => null,
                        'pending_login_token' => null,
                        'last_error' => null,
                    ]);
                }

                $telegram->sendMessage($chatId, $this->welcomeMessage(), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->welcomeKeyboard(),
                ]);

                return response()->json(['ok' => true]);
            }

            if ($text === '/debug_userbot' && $chatId !== '') {
                $account = $this->currentClientAccountForChat($chatId);

                if (! $account) {
                    $telegram->sendMessage($chatId, 'Belum ada data userbot untuk chat ini.');

                    return response()->json(['ok' => true]);
                }

                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>Debug Userbot</b>',
                    '',
                    'Status: <code>'.e($account->auth_status).'</code>',
                    'Nomor: <code>'.e($account->phone_number ?? '-').'</code>',
                    'Session: <code>'.e($account->session_name).'</code>',
                    'Session String: <code>'.e($account->session_string ? 'ada ('.strlen($account->session_string).')' : '-').'</code>',
                    'Code Hash: <code>'.e($account->phone_code_hash ? 'ada ('.strlen($account->phone_code_hash).')' : '-').'</code>',
                    'Pending Session: <code>'.e($account->pending_session_string ? 'ada ('.strlen($account->pending_session_string).')' : '-').'</code>',
                    'Login Token: <code>'.e($account->pending_login_token ? Str::limit($account->pending_login_token, 8, '') : '-').'</code>',
                    'OTP URL: <code>'.e($account->pending_login_token ? $this->otpUrl($account) : '-').'</code>',
                    'Error: <code>'.e($account->last_error ?? '-').'</code>',
                ]), ['parse_mode' => 'HTML']);

                return response()->json(['ok' => true]);
            }

            if ($text === '/debug_worker' && $chatId !== '') {
                $result = $pyrogram->debug();

                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>Debug Worker</b>',
                    '',
                    'OK: <code>'.($result['ok'] ? 'yes' : 'no').'</code>',
                    'Python: <code>'.e($result['python'] ?? '-').'</code>',
                    'Output: <code>'.e($result['output'] ?: '-').'</code>',
                    'Error: <code>'.e(Str::limit($result['error'] ?: '-', 700)).'</code>',
                ]), ['parse_mode' => 'HTML']);

                return response()->json(['ok' => true]);
            }

            if ($text === '/debug_login' && $chatId !== '') {
                $account = $this->currentClientAccountForChat($chatId);

                if (! $account) {
                    $telegram->sendMessage($chatId, 'Belum ada data login untuk chat ini.');

                    return response()->json(['ok' => true]);
                }

                $logPath = storage_path('logs/userbot-login-'.$account->id.'.log');
                $log = is_file($logPath) ? file_get_contents($logPath) : 'Log belum ada.';
                $log = Str::limit(trim((string) $log), 2500);

                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>Debug Login</b>',
                    '',
                    'Account ID: <code>'.e((string) $account->id).'</code>',
                    'Status: <code>'.e($account->auth_status).'</code>',
                    'Laravel DB: <code>'.e(config('database.default').'/'.DB::connection()->getDatabaseName()).'</code>',
                    'Last Error: <code>'.e($account->last_error ?? '-').'</code>',
                    '',
                    '<b>Worker Log</b>',
                    '<code>'.e($log ?: '-').'</code>',
                ]), ['parse_mode' => 'HTML']);

                return response()->json(['ok' => true]);
            }

            if ($text === '/debug_share' && $chatId !== '') {
                $watcher = $pyrogram->ensureShareWatcherRunning();
                $account = TelegramClientAccount::where('bot_chat_id', $chatId)
                    ->where('auth_status', 'authorized')
                    ->latest()
                    ->first() ?: $this->currentClientAccountForChat($chatId);
                $logPath = storage_path('logs/userbot-share-watcher.log');
                $log = $this->tailLog($logPath);
                $sessionFile = $account ? storage_path('app/telegram-sessions/'.$account->session_name.'.session') : null;
                $sessionFileExists = $sessionFile && is_file($sessionFile);
                $needsRelogin = $account
                    && ! $account->session_string
                    && ! $account->pending_session_string
                    && ! $sessionFileExists;

                if ($needsRelogin) {
                    $account->delete();
                    $account = null;
                }

                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>Debug Share Watcher</b>',
                    '',
                    'Ensure: <code>'.($watcher['ok'] ? 'ok' : 'gagal').'</code>',
                    'PID baru: <code>'.e($watcher['output'] ?: '-').'</code>',
                    'Ensure Error: <code>'.e(Str::limit($watcher['error'] ?: '-', 350)).'</code>',
                    'Account ID: <code>'.e($account ? (string) $account->id : '-').'</code>',
                    'Status: <code>'.e($account?->auth_status ?? '-').'</code>',
                    'Session String: <code>'.e($account?->session_string ? 'ada ('.strlen($account->session_string).')' : '-').'</code>',
                    'Pending Session: <code>'.e($account?->pending_session_string ? 'ada ('.strlen($account->pending_session_string).')' : '-').'</code>',
                    'Session File: <code>'.e($sessionFileExists ? 'ada' : '-').'</code>',
                    'Perlu Login Ulang: <code>'.($needsRelogin ? 'ya' : 'tidak').'</code>',
                    '',
                    '<b>Watcher Log</b>',
                    '<code>'.e($log ?: '-').'</code>',
                ]), ['parse_mode' => 'HTML']);

                return response()->json(['ok' => true]);
            }

            if ($chatId !== '' && ($chat['type'] ?? '') === 'private') {
                $account = $this->findAccountForIncomingText($chatId, $from, $text);
                $onboardingText = $account->auth_status === 'awaiting_password' ? $rawText : $text;

                if ($this->handleOnboardingMessage($account, $onboardingText, $telegram, $pyrogram)) {
                    return response()->json(['ok' => true]);
                }
            }

            $result = $engine->process([
                'managed_device_id' => null,
                'group_key' => $chatId,
                'group_name' => $chat['title'] ?? null,
                'sender_key' => (string) ($from['id'] ?? ''),
                'sender_name' => trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')),
                'message_text' => $text,
                'meta' => [
                    'source' => 'telegram_bot',
                    'telegram_update' => $update,
                ],
            ]);

            if (! empty($result['replied']) && ! empty($result['reply_text']) && ! empty($chat['id'])) {
                $telegram->sendMessage($chat['id'], $result['reply_text']);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'update' => $request->all(),
            ]);

            $fallbackChatId = (string) (data_get($request->all(), 'message.chat.id')
                ?? data_get($request->all(), 'callback_query.message.chat.id')
                ?? '');

            if ($fallbackChatId !== '') {
                try {
                    $fallbackLines = [
                        '<b>Sistem sedang diproses ulang.</b>',
                        '',
                        'Coba kirim <code>/start</code> lagi beberapa detik lagi.',
                    ];

                    if (config('app.debug')) {
                        $fallbackLines[] = '';
                        $fallbackLines[] = 'Debug: <code>'.e(Str::limit($e->getMessage(), 250)).'</code>';
                    }

                    $telegram->sendMessage($fallbackChatId, implode("\n", $fallbackLines), ['parse_mode' => 'HTML']);
                } catch (\Throwable $sendError) {
                    Log::error('Telegram webhook fallback message failed', [
                        'message' => $sendError->getMessage(),
                    ]);
                }
            }

            return response()->json(['ok' => true]);
        }
    }

    protected function handleCallbackQuery(
        array $callbackQuery,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): void {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $message = $callbackQuery['message'] ?? [];
        $chat = $message['chat'] ?? [];
        $from = $callbackQuery['from'] ?? [];
        $chatId = (string) ($chat['id'] ?? '');
        $messageId = $message['message_id'] ?? null;

        if ($callbackId !== '') {
            $telegram->answerCallbackQuery($callbackId);
        }

        if ($chatId === '') {
            return;
        }

        if ($data === 'userbot:create') {
            $this->deleteIncompleteClientAccounts($chatId);
            $account = $this->newClientAccount($chatId, $from);

            $account->update([
                'auth_status' => 'awaiting_access_code',
                'phone_code_hash' => null,
                'pending_session_string' => null,
                'pending_2fa_password' => null,
                'pending_login_token' => null,
                'last_error' => null,
                'meta' => null,
                'last_seen_at' => now(),
            ]);

            $telegram->sendMessage($chatId, $this->requestAccessCodeMessage(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->mainMenuKeyboard(),
            ]);

            return;
        }

        if ($data === 'bot:menu') {
            $telegram->sendMessage($chatId, $this->welcomeMessage(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->welcomeKeyboard(),
            ]);

            return;
        }

        if ($data === 'userbot:list') {
            $accounts = TelegramClientAccount::where('bot_chat_id', $chatId)
                ->whereNotNull('phone_number')
                ->where('auth_status', 'authorized')
                ->where(function ($query) {
                    $query->whereNotNull('session_string')
                        ->orWhereNotNull('pending_session_string');
                })
                ->latest()
                ->get();

            if ($accounts->isEmpty()) {
                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>🤖 Belum ada userbot aktif.</b>',
                    '',
                    $this->quote('Klik 🚀 Buat Userbot untuk menghubungkan akun Telegram pertama kamu.'),
                ]), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->mainMenuKeyboard(),
                ]);

                return;
            }

            $telegram->sendMessage($chatId, implode("\n", [
                '<b>⚙️ Pilih userbot</b>',
                '',
                $this->quote('Pilih akun yang mau kamu atur grup share-nya.'),
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->userbotListKeyboard($accounts),
            ]);

            return;
        }

        if (str_starts_with($data, 'userbot:settings:')) {
            $account = $this->accountFromCallback($chatId, $data, 'userbot:settings:');

            if (! $account) {
                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>⚠️ Userbot tidak ditemukan.</b>',
                    '',
                    $this->quote('Userbot tidak valid atau bukan milik chat ini.'),
                ]), ['parse_mode' => 'HTML']);

                return;
            }

            $telegram->sendMessage($chatId, $this->userbotSettingsMessage($account), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->userbotSettingsKeyboard($account),
            ]);

            return;
        }

        if (str_starts_with($data, 'userbot:add_group:')) {
            $account = $this->authorizedAccountFromCallback($chatId, $data, 'userbot:add_group:');

            if (! $account) {
                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>⚠️ Userbot belum aktif.</b>',
                    '',
                    $this->quote('Pastikan userbot sudah login dan terhubung ke chat ini.'),
                ]), ['parse_mode' => 'HTML']);

                return;
            }

            $this->sendUserbotGroupPicker($telegram, $pyrogram, $chatId, $account, $messageId);

            return;
        }

        if (str_starts_with($data, 'userbot:toggle_group:')) {
            $parts = explode(':', $data, 4);
            $accountId = (int) ($parts[2] ?? 0);
            $chatIdToToggle = (string) ($parts[3] ?? '');
            $account = TelegramClientAccount::where('bot_chat_id', $chatId)
                ->whereKey($accountId)
                ->where('auth_status', 'authorized')
                ->first();

            if (! $account || $chatIdToToggle === '') {
                $telegram->sendMessage($chatId, implode("\n", [
                    '<b>⚠️ Grup tidak valid.</b>',
                    '',
                    $this->quote('Coba buka ulang menu grup dari setting userbot.'),
                ]), ['parse_mode' => 'HTML']);

                return;
            }

            $group = TelegramClientGroup::where('telegram_client_account_id', $account->id)
                ->where('chat_id', $chatIdToToggle)
                ->first();

            if ($group) {
                $group->update([
                    'status' => $group->status === 'active' ? 'inactive' : 'active',
                    'last_verified_at' => now(),
                ]);
            }

            $this->sendUserbotGroupPicker($telegram, $pyrogram, $chatId, $account->fresh(), $messageId, refreshGroups: false);

            return;
        }

        if ($data === 'bot:about') {
            $telegram->sendMessage($chatId, implode("\n", [
                '<b>✨ Tentang VixStore AutoShare</b>',
                '',
                $this->quote('Kelola userbot Telegram untuk share promosi ke grup yang sudah kamu pilih sendiri.'),
                '',
                '1. Buat userbot',
                '2. Pilih grup target',
                '3. Share pesan lebih cepat lewat forward',
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->mainMenuKeyboard(),
            ]);

            return;
        }

        if ($data === 'bot:rules') {
            $telegram->sendMessage($chatId, implode("\n", [
                '<b>📜 Rules Penggunaan</b>',
                '',
                $this->quote('Gunakan fitur share dengan rapi supaya akun tetap aman dan grup tetap nyaman.'),
                '',
                '1. Gunakan hanya untuk grup yang kamu ikuti.',
                '2. Jangan kirim spam berlebihan.',
                '3. Pakai jeda kirim kalau target grup banyak.',
                '4. Limit akun Telegram menjadi tanggung jawab pemilik akun.',
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->mainMenuKeyboard(),
            ]);
        }
    }

    protected function authorizedAccountFromCallback(
        string $botChatId,
        string $data,
        string $prefix
    ): ?TelegramClientAccount {
        return $this->accountFromCallback($botChatId, $data, $prefix, ['authorized']);
    }

    protected function accountFromCallback(
        string $botChatId,
        string $data,
        string $prefix,
        ?array $statuses = null
    ): ?TelegramClientAccount {
        $accountId = (int) Str::after($data, $prefix);

        if ($accountId <= 0) {
            return null;
        }

        $query = TelegramClientAccount::where('bot_chat_id', $botChatId)
            ->whereKey($accountId)
            ->whereNotNull('phone_number');

        if ($statuses !== null) {
            $query->whereIn('auth_status', $statuses);
        }

        return $query->first();
    }

    protected function sendUserbotGroupPicker(
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram,
        string $chatId,
        TelegramClientAccount $account,
        $messageId = null,
        bool $refreshGroups = true
    ): void {
        $this->sendOrEditBotMessage($telegram, $chatId, $messageId, implode("\n", [
            '<b>🔎 Mengambil daftar grup...</b>',
            '',
            $this->quote('Sebentar ya, bot sedang membaca grup dari akun userbot kamu.'),
        ]), [
            'parse_mode' => 'HTML',
        ]);

        $hasFreshGroups = TelegramClientGroup::where('telegram_client_account_id', $account->id)
            ->whereNotNull('chat_id')
            ->where('last_verified_at', '>=', now()->subMinutes(10))
            ->exists();

        $groups = [];

        if ($refreshGroups && ! $hasFreshGroups) {
            $result = $pyrogram->listGroups($account);
            $groups = $result['data']['groups'] ?? [];

            if (! $result['ok'] || ! is_array($groups)) {
                $this->sendOrEditBotMessage($telegram, $chatId, $messageId, implode("\n", [
                    '<b>⚠️ Belum bisa mengambil grup.</b>',
                    '',
                    $this->quote('Worker belum berhasil membaca daftar grup.'),
                    '',
                    $this->formatWorkerErrorForTelegram($result, 'Alasan'),
                ]), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->userbotSettingsKeyboard($account),
                ]);

                return;
            }

            foreach ($groups as $group) {
                $this->syncClientGroup($account, $group);
            }
        }

        $account = $account->fresh();

        $storedGroupsCount = TelegramClientGroup::where('telegram_client_account_id', $account->id)
            ->whereNotNull('chat_id')
            ->count();

        if ($storedGroupsCount === 0) {
            $this->sendOrEditBotMessage($telegram, $chatId, $messageId, implode("\n", [
                '<b>📭 Belum ada grup yang terbaca.</b>',
                '',
                $this->quote('Pastikan akun userbot sudah join ke grup target promosi.'),
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->userbotSettingsKeyboard($account),
            ]);

            return;
        }

        $this->sendOrEditBotMessage($telegram, $chatId, $messageId, implode("\n", [
            '<b>📌 Pilih grup target promosi</b>',
            '',
            $this->quote('Klik nama grup untuk masuk atau keluar dari list share. Grup bertanda [ON] akan menerima pesan.'),
        ]), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->groupPickerKeyboard($account),
        ]);
    }

    protected function sendOrEditBotMessage(
        TelegramBotService $telegram,
        string $chatId,
        $messageId,
        string $text,
        array $extra = []
    ): void {
        if ($messageId) {
            $result = $telegram->editMessageText($chatId, $messageId, $text, $extra);

            if ($result['ok'] ?? false) {
                return;
            }
        }

        $telegram->sendMessage($chatId, $text, $extra);
    }

    protected function syncClientGroup(TelegramClientAccount $account, array $group): TelegramClientGroup
    {
        $clientGroup = TelegramClientGroup::firstOrNew([
            'telegram_client_account_id' => $account->id,
            'chat_id' => (string) ($group['chat_id'] ?? ''),
        ]);

        $clientGroup->fill([
            'username' => $group['username'] ?? null,
            'title' => $group['title'] ?? $group['chat_id'] ?? 'Grup Telegram',
            'status' => $clientGroup->exists ? $clientGroup->status : 'inactive',
            'last_verified_at' => now(),
            'last_error' => null,
            'meta' => [
                'source' => 'pyrogram_dialogs',
                'type' => $group['type'] ?? null,
            ],
        ]);
        $clientGroup->save();

        return $clientGroup;
    }

    protected function userbotListKeyboard($accounts): array
    {
        $rows = [];

        foreach ($accounts as $account) {
            $label = trim(($account->phone_number ?? 'Userbot').' • '.$account->auth_status);

            $rows[] = [[
                'text' => Str::limit($label, 60, '...'),
                'callback_data' => 'userbot:settings:'.$account->id,
            ]];
        }

        $rows[] = [[
            'text' => '🚀 Buat Userbot Baru',
            'callback_data' => 'userbot:create',
        ]];

        $rows[] = [[
            'text' => '🏠 Kembali ke Menu Utama',
            'callback_data' => 'bot:menu',
        ]];

        return ['inline_keyboard' => $rows];
    }

    protected function userbotSettingsMessage(TelegramClientAccount $account): string
    {
        $activeGroups = TelegramClientGroup::where('telegram_client_account_id', $account->id)
            ->where('status', 'active')
            ->count();

        return implode("\n", [
            '<b>⚙️ Setting Userbot</b>',
            '',
            'Nomor: <code>'.e($account->phone_number ?? '-').'</code>',
            'Status: <code>'.e($account->auth_status).'</code>',
            'Grup aktif: <code>'.$activeGroups.'</code>',
            $account->auth_status === 'error' && $account->last_error
                ? 'Error: <code>'.e(Str::limit($account->last_error, 300)).'</code>'
                : null,
            '',
            $account->auth_status === 'error'
                ? $this->quote('Session userbot ini sudah tidak valid. Buat userbot baru lalu login ulang.')
                : $this->quote('Pilih menu di bawah untuk mengatur target share.'),
        ]);
    }

    protected function userbotSettingsKeyboard(TelegramClientAccount $account): array
    {
        $rows = [];

        if ($account->auth_status === 'authorized') {
            $rows[] = [[
                'text' => '📌 Add Grup',
                'callback_data' => 'userbot:add_group:'.$account->id,
            ]];
        }

        if ($account->auth_status === 'error') {
            $rows[] = [[
                'text' => '🚀 Buat Userbot Baru',
                'callback_data' => 'userbot:create',
            ]];
        }

        $rows[] = [
            [
                'text' => '⬅️ Kembali ke List Bot',
                'callback_data' => 'userbot:list',
            ],
        ];

        $rows[] = [[
            'text' => '🏠 Kembali ke Menu Utama',
            'callback_data' => 'bot:menu',
        ]];

        return [
            'inline_keyboard' => $rows,
        ];
    }

    protected function groupPickerKeyboard(TelegramClientAccount $account): array
    {
        $groups = TelegramClientGroup::where('telegram_client_account_id', $account->id)
            ->whereNotNull('chat_id')
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('title')
            ->limit(40)
            ->get();
        $rows = [];

        foreach ($groups as $group) {
            $prefix = $group->status === 'active' ? '[ON] ' : '[ ] ';
            $title = $group->title ?: $group->chat_id;

            $rows[] = [[
                'text' => Str::limit($prefix.$title, 60, '...'),
                'callback_data' => 'userbot:toggle_group:'.$account->id.':'.$group->chat_id,
            ]];
        }

        $rows[] = [[
            'text' => '⬅️ Kembali ke Setting',
            'callback_data' => 'userbot:settings:'.$account->id,
        ]];

        $rows[] = [[
            'text' => '🏠 Kembali ke Menu Utama',
            'callback_data' => 'bot:menu',
        ]];

        return ['inline_keyboard' => $rows];
    }

    protected function handleOnboardingMessage(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): bool {
        if ($text === '') {
            return false;
        }

        if ($account->auth_status === 'awaiting_access_code') {
            return $this->handleAccessCode($account, $text, $telegram);
        }

        if ($account->auth_status === 'awaiting_phone') {
            return $this->handlePhoneNumber($account, $text, $telegram, $pyrogram);
        }

        if ($account->auth_status === 'awaiting_code') {
            return $this->handleOtpCode($account, $text, $telegram, $pyrogram);
        }

        if ($account->auth_status === 'awaiting_password') {
            return $this->handleTwoFactorPassword($account, $text, $telegram, $pyrogram);
        }

        if ($this->looksLikePhoneNumber($text) || $this->looksLikeOtpCode($text)) {
            $telegram->sendMessage($account->bot_chat_id, $this->wrongFlowMessage(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->welcomeKeyboard(),
            ]);

            return true;
        }

        if ($account->auth_status === 'idle') {
            $telegram->sendMessage($account->bot_chat_id, $this->welcomeMessage(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->welcomeKeyboard(),
            ]);

            return true;
        }

        return false;
    }

    protected function handleAccessCode(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram
    ): bool {
        $codeText = $this->normalizeAccessCode($text);
        $accessCode = TelegramAccessCode::where('code', $codeText)->first();

        if (! $accessCode || ! $accessCode->isAvailable()) {
            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>⚠️ Kode akses tidak valid.</b>',
                '',
                $this->quote('Pastikan kode masih aktif, belum expired, dan belum melewati batas pemakaian. Hubungi admin kalau belum punya kode.'),
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->mainMenuKeyboard(),
            ]);

            return true;
        }

        DB::transaction(function () use ($account, $accessCode, $codeText) {
            $accessCode->markUsed();

            $account->fresh()->update([
                'auth_status' => 'awaiting_phone',
                'last_seen_at' => now(),
                'meta' => array_merge($account->meta ?? [], [
                    'access_code' => [
                        'id' => $accessCode->id,
                        'code' => $codeText,
                        'validated_at' => now()->toISOString(),
                    ],
                ]),
            ]);
        });

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>✅ Kode akses diterima.</b>',
            '',
            $this->quote('Sekarang kirim nomor Telegram yang ingin kamu hubungkan.'),
            '',
            'Contoh: <code>+6281234567890</code>',
        ]), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->mainMenuKeyboard(),
        ]);

        return true;
    }

    protected function handlePhoneNumber(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): bool {
        $phoneNumber = $this->normalizePhoneNumber($text);

        if (! $phoneNumber) {
            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>⚠️ Format nomor belum valid.</b>',
                '',
                $this->quote('Kirim nomor Telegram dengan kode negara.'),
                '',
                'Contoh: <code>+6281234567890</code>',
            ]), ['parse_mode' => 'HTML']);

            return true;
        }

        $existingAccount = TelegramClientAccount::where('phone_number', $phoneNumber)
            ->whereKeyNot($account->id)
            ->first();

        if ($existingAccount) {
            $sameChat = $existingAccount->bot_chat_id === $account->bot_chat_id;
            $sameOwner = $sameChat
                || ($account->bot_user_id !== null && $existingAccount->bot_user_id === $account->bot_user_id);

            if (! $sameOwner) {
                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>⚠️ Nomor ini sudah terdaftar.</b>',
                    '',
                    $this->quote('Nomor tersebut sudah dipakai di akun userbot lain. Kalau ini nomor kamu, hubungi admin untuk reset data lama.'),
                ]), ['parse_mode' => 'HTML']);

                $account->delete();

                return true;
            }

            if (! $sameChat) {
                if ($account->phone_number !== null || $account->auth_status === 'authorized') {
                    $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                        '<b>ℹ️ Nomor ini sudah ada di data lama.</b>',
                        '',
                        $this->quote('Data saat ini belum bisa digabung otomatis. Hubungi admin untuk reset data lama nomor ini.'),
                    ]), ['parse_mode' => 'HTML']);

                    return true;
                }

                $account->delete();
            }

            $existingAccount->forceFill([
                'bot_chat_id' => $sameChat ? $existingAccount->bot_chat_id : $account->bot_chat_id,
                'bot_user_id' => $account->bot_user_id,
                'bot_username' => $account->bot_username,
                'bot_first_name' => $account->bot_first_name,
                'last_seen_at' => now(),
                'is_active' => true,
            ])->save();

            $account = $existingAccount->fresh();
        }

        $loginToken = (string) Str::uuid();

        $account->update([
            'phone_number' => $phoneNumber,
            'auth_status' => 'sending_code',
            'phone_code_hash' => null,
            'pending_otp_code' => null,
            'session_string' => null,
            'pending_session_string' => null,
            'pending_2fa_password' => null,
            'pending_login_token' => $loginToken,
            'last_error' => null,
            'last_seen_at' => now(),
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>✅ Nomor diterima.</b>',
            '',
            "📱 Nomor: <code>{$phoneNumber}</code>",
            '',
            $this->quote('Sebentar, sistem sedang meminta kode OTP Telegram.'),
        ]), ['parse_mode' => 'HTML']);

        $result = $pyrogram->startLoginFlow($account->fresh(), $loginToken);

        if ($result['ok']) {
            $pyrogram->ensureShareWatcherRunning();

            $freshAccount = $this->waitForLoginWorkerStatus($account->fresh(), $loginToken);

            if (! $freshAccount) {
                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>⚠️ Login gagal.</b>',
                    '',
                    $this->quote('Data percobaan login sudah dibersihkan. Klik 🚀 Buat Userbot untuk mencoba ulang.'),
                ]), ['parse_mode' => 'HTML']);

                return true;
            }

            if ($freshAccount && $freshAccount->last_error) {
                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>⚠️ Belum bisa meminta kode OTP.</b>',
                    '',
                    $this->quote('Worker Pyrogram berhenti sebelum Telegram mengirim kode.'),
                    'Alasan: <code>'.e(Str::limit($freshAccount->last_error, 350)).'</code>',
                    '',
                    'Klik <b>🚀 Buat Userbot</b> untuk mencoba ulang.',
                ]), ['parse_mode' => 'HTML']);

                $freshAccount->delete();

                return true;
            }

            if ($freshAccount && $freshAccount->auth_status === 'awaiting_code') {
                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>📩 Kode OTP sudah diminta ke Telegram.</b>',
                    '',
                    $this->quote('Kalau kode belum muncul, tunggu sebentar lalu cek aplikasi Telegram akun tersebut.'),
                    '',
                    '🔐 Masukkan OTP lewat tombol di bawah.',
                ]), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->otpLinkKeyboard($freshAccount),
                ]);

                return true;
            }

            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>⏳ Permintaan login sedang diproses.</b>',
                '',
                $this->quote('Tunggu kode OTP dari Telegram. Setelah kode masuk, buka halaman input OTP lewat tombol di bawah.'),
                '',
                '🚫 Jangan kirim kode OTP langsung di chat bot.',
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->otpLinkKeyboard($account->fresh()),
            ]);

            return true;
        }

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>⚠️ Belum bisa mengirim OTP.</b>',
            '',
            $this->quote('Kemungkinan worker Pyrogram belum siap atau konfigurasi API ID/API HASH belum benar.'),
            $this->formatWorkerErrorForTelegram($result, 'Detail'),
            '',
            'Coba lagi beberapa saat, atau hubungi admin.',
        ]), ['parse_mode' => 'HTML']);

        $account->delete();

        return true;
    }

    protected function waitForLoginWorkerStatus(TelegramClientAccount $account, string $loginToken): ?TelegramClientAccount
    {
        $deadline = microtime(true) + 6;

        do {
            $freshAccount = $account->fresh();

            if (! $freshAccount || $freshAccount->pending_login_token !== $loginToken) {
                return $freshAccount;
            }

            if ($freshAccount->auth_status !== 'sending_code' || $freshAccount->last_error) {
                return $freshAccount;
            }

            usleep(500_000);
        } while (microtime(true) < $deadline);

        return $account->fresh();
    }

    protected function handleOtpCode(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): bool {
        $code = preg_replace('/\D+/', '', $text);

        if (strlen($code) < 4) {
            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>⚠️ Kode OTP belum valid.</b>',
                '',
                $this->quote('Kirim angka OTP yang muncul dari Telegram.'),
            ]), ['parse_mode' => 'HTML']);

            return true;
        }

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>✅ Kode diterima.</b>',
            '',
            $this->quote('Sistem sedang mencoba login ke akun Telegram kamu.'),
        ]), ['parse_mode' => 'HTML']);
        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>🔐 Jangan kirim OTP di chat.</b>',
            '',
            $this->quote('Untuk menghindari blokir keamanan Telegram, masukkan kode OTP lewat halaman web yang aman.'),
        ]), [
            'parse_mode' => 'HTML',
            'reply_markup' => $this->otpLinkKeyboard($account),
        ]);

        return true;
    }

    protected function handleTwoFactorPassword(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): bool {
        $account->fresh()->update([
            'auth_status' => 'awaiting_password',
            'pending_2fa_password' => $text,
            'last_error' => null,
            'last_seen_at' => now(),
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>🔐 Password 2FA diterima.</b>',
            '',
            $this->quote('Sedang menyelesaikan login akun Telegram kamu.'),
        ]), ['parse_mode' => 'HTML']);

        return true;
    }

    protected function registerClientAccount(string $chatId, array $from): TelegramClientAccount
    {
        $account = TelegramClientAccount::where('bot_chat_id', $chatId)
            ->whereNull('phone_number')
            ->whereNotIn('auth_status', ['authorized'])
            ->latest()
            ->first() ?? new TelegramClientAccount(['bot_chat_id' => $chatId]);

        if (! $account->exists) {
            $account->session_name = 'client_'.Str::slug($chatId).'_'.Str::lower(Str::random(8));
            $account->auth_status = 'idle';
        }

        $account->fill([
            'bot_user_id' => isset($from['id']) ? (string) $from['id'] : null,
            'bot_username' => $from['username'] ?? null,
            'bot_first_name' => $from['first_name'] ?? null,
            'last_seen_at' => now(),
            'is_active' => true,
        ]);
        $account->save();

        return $account;
    }

    protected function deleteIncompleteClientAccounts(string $chatId): void
    {
        TelegramClientAccount::where('bot_chat_id', $chatId)
            ->where(function ($query) {
                $query->where('auth_status', '!=', 'authorized')
                    ->orWhere(function ($query) {
                        $query->where('auth_status', 'authorized')
                            ->whereNull('session_string')
                            ->whereNull('pending_session_string');
                    });
            })
            ->delete();
    }

    protected function findOrRegisterClientAccount(string $chatId, array $from): TelegramClientAccount
    {
        return $this->currentClientAccountForChat($chatId, includeAuthorized: false)
            ?? $this->registerClientAccount($chatId, $from);
    }

    protected function findAccountForIncomingText(string $chatId, array $from, string $text): TelegramClientAccount
    {
        if ($this->looksLikePhoneNumber($text)) {
            $awaitingPhone = TelegramClientAccount::where('bot_chat_id', $chatId)
                ->where('auth_status', 'awaiting_phone')
                ->latest()
                ->first();

            if ($awaitingPhone) {
                return $awaitingPhone;
            }
        }

        if ($this->looksLikeOtpCode($text)) {
            $awaitingCode = TelegramClientAccount::where('bot_chat_id', $chatId)
                ->whereIn('auth_status', ['awaiting_code', 'sending_code'])
                ->latest()
                ->first();

            if ($awaitingCode) {
                return $awaitingCode;
            }
        }

        return $this->findOrRegisterClientAccount($chatId, $from);
    }

    protected function currentClientAccountForChat(string $chatId, bool $includeAuthorized = true): ?TelegramClientAccount
    {
        $query = TelegramClientAccount::where('bot_chat_id', $chatId);

        if (! $includeAuthorized) {
            $query->whereIn('auth_status', ['awaiting_access_code', 'awaiting_phone', 'sending_code', 'awaiting_code', 'awaiting_password', 'idle']);
        }

        return $query
            ->orderByRaw("
                case
                    when auth_status in ('sending_code', 'awaiting_code', 'awaiting_password') then 0
                    when auth_status = 'awaiting_access_code' then 1
                    when auth_status = 'awaiting_phone' then 2
                    when auth_status = 'authorized' then 3
                    else 4
                end
            ")
            ->latest()
            ->first();
    }

    protected function newClientAccount(string $chatId, array $from): TelegramClientAccount
    {
        $account = new TelegramClientAccount([
            'bot_chat_id' => $chatId,
            'session_name' => 'client_'.Str::slug($chatId).'_'.Str::lower(Str::random(8)),
            'auth_status' => 'idle',
        ]);

        $account->fill([
            'bot_user_id' => isset($from['id']) ? (string) $from['id'] : null,
            'bot_username' => $from['username'] ?? null,
            'bot_first_name' => $from['first_name'] ?? null,
            'last_seen_at' => now(),
            'is_active' => true,
        ]);
        $account->save();

        return $account;
    }

    protected function normalizePhoneNumber(string $text): ?string
    {
        $phoneNumber = preg_replace('/[^\d+]+/', '', trim($text));

        if ($phoneNumber === null || $phoneNumber === '') {
            return null;
        }

        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '+62'.substr($phoneNumber, 1);
        } elseif (! str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+'.ltrim($phoneNumber, '0');
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $phoneNumber)) {
            return null;
        }

        return $phoneNumber;
    }

    protected function normalizeAccessCode(string $text): string
    {
        return strtoupper(trim($text));
    }

    protected function looksLikePhoneNumber(string $text): bool
    {
        $clean = preg_replace('/[^\d+]+/', '', trim($text));

        return is_string($clean)
            && preg_match('/^(\+?\d{8,15}|0\d{8,14})$/', $clean) === 1;
    }

    protected function looksLikeOtpCode(string $text): bool
    {
        return preg_match('/^\s*\d{4,8}\s*$/', $text) === 1;
    }

    protected function wrongFlowMessage(): string
    {
        return implode("\n", [
            '<b>✨ Mulai dari menu dulu.</b>',
            '',
            $this->quote('Untuk membuat userbot, klik tombol 🚀 Buat Userbot lalu ikuti instruksi nomor dan OTP dari bot ini.'),
        ]);
    }

    protected function requestAccessCodeMessage(): string
    {
        return implode("\n", [
            '<b>🔐 Kode Akses Userbot</b>',
            '',
            $this->quote('Masukkan kode akses dari admin dulu sebelum membuat userbot.'),
            '',
            'Kirim kode akses di chat ini.',
        ]);
    }

    protected function formatWorkerErrorForTelegram(array $result, string $label = 'Detail'): string
    {
        $error = trim((string) ($result['error'] ?: $result['output'] ?: 'Tidak ada detail error.'));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $error) ?: [])));

        $important = collect($lines)->first(function (string $line) {
            return str_contains($line, 'Telegram says:')
                || str_contains($line, 'ModuleNotFoundError')
                || str_contains($line, 'RuntimeError')
                || str_contains($line, 'struct.error')
                || str_contains($line, 'Database connection failed');
        });

        $error = $important ?: implode("\n", array_slice($lines, -3));
        $error = Str::limit($error, 350);

        return $label.': <code>'.e($error).'</code>';
    }

    protected function tailLog(string $path, int $maxLines = 80, int $maxChars = 3000): string
    {
        if (! is_file($path)) {
            return 'Log belum ada.';
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $tail = implode("\n", array_slice($lines, -$maxLines));

        return Str::limit(trim($tail), $maxChars);
    }

    protected function requestPhoneMessage(): string
    {
        return implode("\n", [
            '<b>🚀 Buat Userbot</b>',
            '',
            $this->quote('Kirim nomor Telegram yang ingin kamu hubungkan.'),
            '',
            'Format wajib pakai kode negara.',
            'Contoh: <code>+6281234567890</code>',
            '',
            'Setelah itu Telegram akan mengirim kode OTP ke akun tersebut.',
            'Masukkan OTP lewat tombol web yang bot kirim, bukan lewat chat.',
        ]);
    }

    protected function otpLinkKeyboard(TelegramClientAccount $account): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🔐 Input OTP',
                        'url' => $this->otpUrl($account),
                    ],
                ],
                [
                    [
                        'text' => '🏠 Kembali ke Menu Utama',
                        'callback_data' => 'bot:menu',
                    ],
                ],
            ],
        ];
    }

    protected function otpUrl(TelegramClientAccount $account): string
    {
        $url = url()->route('telegram-login.show', [
            'account' => $account->id,
            'token' => $account->pending_login_token,
        ]);

        if (str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, 7);
        }

        return $url;
    }

    protected function welcomeMessage(): string
    {
        return implode("\n", [
            '<b>✨ Selamat datang di VixStore AutoShare</b>',
            '',
            $this->quote('Kelola userbot Telegram untuk bantu share promosi jualan ke grup-grup yang sudah kamu daftarkan.'),
            '',
            '<b>Yang bisa kamu lakukan:</b>',
            '1. Membuat userbot dari akun Telegram kamu',
            '2. Menyimpan daftar grup target promosi',
            '3. Mengirim pesan promosi dengan command <code>!share</code>',
            '4. Melihat status userbot dan rules penggunaan',
            '',
            'Pilih menu di bawah untuk mulai.',
        ]);
    }

    protected function welcomeKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🚀 Buat Userbot',
                        'callback_data' => 'userbot:create',
                    ],
                    [
                        'text' => '🤖 List Bot',
                        'callback_data' => 'userbot:list',
                    ],
                ],
                [
                    [
                        'text' => '✨ Tentang Bot',
                        'callback_data' => 'bot:about',
                    ],
                    [
                        'text' => '📜 Rules',
                        'callback_data' => 'bot:rules',
                    ],
                ],
            ],
        ];
    }

    protected function mainMenuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🏠 Kembali ke Menu Utama',
                        'callback_data' => 'bot:menu',
                    ],
                ],
            ],
        ];
    }

    protected function quote(string ...$lines): string
    {
        return '<blockquote>'.implode("\n", array_filter($lines, fn ($line) => $line !== '')).'</blockquote>';
    }
}
