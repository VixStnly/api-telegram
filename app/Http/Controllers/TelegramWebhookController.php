<?php

namespace App\Http\Controllers;

use App\Models\TelegramClientAccount;
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
            $callbackQuery = $update['callback_query'] ?? null;

            if ($callbackQuery) {
                $this->handleCallbackQuery($callbackQuery, $telegram);

                return response()->json(['ok' => true]);
            }

            $message = $update['message'] ?? null;

            if (! $message) {
                return response()->json(['ok' => true]);
            }

            $chat = $message['chat'] ?? [];
            $from = $message['from'] ?? [];
            $text = trim((string) ($message['text'] ?? ''));
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
                $account->update([
                    'auth_status' => 'idle',
                    'phone_code_hash' => null,
                    'pending_otp_code' => null,
                    'pending_session_string' => null,
                    'pending_login_token' => null,
                    'last_error' => null,
                ]);

                $telegram->sendMessage($chatId, $this->welcomeMessage(), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->welcomeKeyboard(),
                ]);

                return response()->json(['ok' => true]);
            }

            if ($text === '/debug_userbot' && $chatId !== '') {
                $account = TelegramClientAccount::where('bot_chat_id', $chatId)->first();

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
                $account = TelegramClientAccount::where('bot_chat_id', $chatId)->first();

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

            if ($chatId !== '' && ($chat['type'] ?? '') === 'private') {
                $account = $this->findOrRegisterClientAccount($chatId, $from);

                if ($this->handleOnboardingMessage($account, $text, $telegram, $pyrogram)) {
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

    protected function handleCallbackQuery(array $callbackQuery, TelegramBotService $telegram): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $message = $callbackQuery['message'] ?? [];
        $chat = $message['chat'] ?? [];
        $from = $callbackQuery['from'] ?? [];
        $chatId = (string) ($chat['id'] ?? '');

        if ($callbackId !== '') {
            $telegram->answerCallbackQuery($callbackId);
        }

        if ($chatId === '') {
            return;
        }

        if ($data === 'userbot:create') {
            $account = $this->findOrRegisterClientAccount($chatId, $from);
            $account->update([
                'auth_status' => 'awaiting_phone',
                'phone_code_hash' => null,
                'pending_session_string' => null,
                'pending_login_token' => null,
                'last_error' => null,
                'last_seen_at' => now(),
            ]);

            $telegram->sendMessage($chatId, $this->requestPhoneMessage(), [
                'parse_mode' => 'HTML',
            ]);

            return;
        }

        if ($data === 'userbot:list') {
            $account = TelegramClientAccount::where('bot_chat_id', $chatId)->first();
            $status = $account?->auth_status ?? 'belum dibuat';
            $phone = $account?->phone_number ?? '-';

            $telegram->sendMessage($chatId, implode("\n", [
                '<b>List Userbot Kamu</b>',
                '',
                "Nomor: {$phone}",
                "Status: {$status}",
                '',
                'Nanti di sini akan tampil semua userbot aktif dan daftar grupnya.',
            ]), ['parse_mode' => 'HTML']);

            return;
        }

        if ($data === 'bot:about') {
            $telegram->sendMessage($chatId, implode("\n", [
                '<b>Tentang VixStore AutoShare</b>',
                '',
                'Bot ini membantu pelanggan menghubungkan akun Telegram mereka sebagai userbot untuk share promosi ke grup yang mereka daftarkan sendiri.',
                '',
                'Fitur login userbot sedang disambungkan bertahap.',
            ]), ['parse_mode' => 'HTML']);

            return;
        }

        if ($data === 'bot:rules') {
            $telegram->sendMessage($chatId, implode("\n", [
                '<b>Rules Penggunaan</b>',
                '',
                '1. Gunakan hanya untuk grup yang kamu ikuti dan izinkan promosi.',
                '2. Jangan mengirim spam berlebihan.',
                '3. Akun Telegram yang terkena limit menjadi tanggung jawab pemilik akun.',
                '4. Gunakan jeda kirim agar akun tetap aman.',
            ]), ['parse_mode' => 'HTML']);
        }
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

    protected function handlePhoneNumber(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): bool {
        $phoneNumber = $this->normalizePhoneNumber($text);

        if (! $phoneNumber) {
            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>Format nomor belum valid.</b>',
                '',
                'Kirim nomor Telegram dengan kode negara.',
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
                $account->update([
                    'auth_status' => 'idle',
                    'last_error' => 'PHONE_ALREADY_REGISTERED',
                    'last_seen_at' => now(),
                ]);

                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>Nomor ini sudah terdaftar.</b>',
                    '',
                    'Nomor tersebut sudah dipakai di akun userbot lain.',
                    'Kalau ini nomor kamu, hubungi admin untuk reset data lama.',
                ]), ['parse_mode' => 'HTML']);

                return true;
            }

            if (! $sameChat) {
                if ($account->phone_number !== null || $account->auth_status === 'authorized') {
                    $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                        '<b>Nomor ini sudah ada di data lama.</b>',
                        '',
                        'Data saat ini belum bisa digabung otomatis. Hubungi admin untuk reset data lama nomor ini.',
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
            'pending_session_string' => null,
            'pending_login_token' => $loginToken,
            'last_error' => null,
            'last_seen_at' => now(),
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>Nomor diterima.</b>',
            '',
            "Nomor: <code>{$phoneNumber}</code>",
            'Sebentar, sistem sedang meminta kode OTP Telegram...',
        ]), ['parse_mode' => 'HTML']);

        $result = $pyrogram->startLoginFlow($account->fresh(), $loginToken);

        if ($result['ok']) {
            $freshAccount = $this->waitForLoginWorkerStatus($account->fresh(), $loginToken);

            if ($freshAccount && $freshAccount->last_error) {
                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>Belum bisa meminta kode OTP.</b>',
                    '',
                    'Worker Pyrogram berhenti sebelum Telegram mengirim kode.',
                    'Alasan: <code>'.e(Str::limit($freshAccount->last_error, 350)).'</code>',
                    '',
                    'Klik <b>Buat Userbot</b> untuk mencoba ulang.',
                ]), ['parse_mode' => 'HTML']);

                return true;
            }

            if ($freshAccount && $freshAccount->auth_status === 'awaiting_code') {
                $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                    '<b>Kode OTP sudah diminta ke Telegram.</b>',
                    '',
                    'Kalau kode belum muncul, tunggu sebentar lalu cek aplikasi Telegram akun tersebut.',
                    'Masukkan OTP lewat tombol di bawah.',
                ]), [
                    'parse_mode' => 'HTML',
                    'reply_markup' => $this->otpLinkKeyboard($freshAccount),
                ]);

                return true;
            }

            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>Permintaan login sedang diproses.</b>',
                '',
                'Tunggu kode OTP dari Telegram. Setelah kode masuk, buka halaman input OTP lewat tombol di bawah.',
                '',
                'Jangan kirim kode OTP langsung di chat bot.',
            ]), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->otpLinkKeyboard($account->fresh()),
            ]);

            return true;
        }

        $account->fresh()->update([
            'auth_status' => 'idle',
            'pending_login_token' => null,
            'last_error' => $result['error'] ?: $result['output'],
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>Belum bisa mengirim OTP.</b>',
            '',
            'Kemungkinan worker Pyrogram belum siap atau konfigurasi API ID/API HASH belum benar.',
            $this->formatWorkerErrorForTelegram($result, 'Detail'),
            'Coba lagi beberapa saat, atau hubungi admin.',
        ]), ['parse_mode' => 'HTML']);

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
            $telegram->sendMessage($account->bot_chat_id, 'Kode OTP belum valid. Kirim angka OTP dari Telegram.');

            return true;
        }

        $telegram->sendMessage($account->bot_chat_id, 'Kode diterima. Sedang mencoba login ke akun Telegram kamu...');
        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>Jangan kirim OTP di chat.</b>',
            '',
            'Untuk menghindari blokir keamanan Telegram, masukkan kode OTP lewat halaman web yang aman.',
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
        $telegram->sendMessage($account->bot_chat_id, 'Password diterima. Sedang menyelesaikan login...');

        $result = $pyrogram->signIn($account, '', $text);
        $status = $result['data']['status'] ?? null;

        if ($result['ok'] && $status === 'authorized') {
            $account->fresh()->update([
                'auth_status' => 'authorized',
                'bot_username' => $result['data']['telegram_username'] ?? $account->bot_username,
                'phone_code_hash' => null,
                'pending_session_string' => null,
                'last_error' => null,
                'last_login_at' => now(),
                'last_seen_at' => now(),
            ]);

            $telegram->sendMessage($account->bot_chat_id, '<b>Userbot berhasil dibuat.</b>', [
                'parse_mode' => 'HTML',
            ]);

            return true;
        }

        $account->fresh()->update([
            'auth_status' => 'awaiting_password',
            'last_error' => $result['error'] ?: $result['output'],
        ]);

        $telegram->sendMessage($account->bot_chat_id, 'Password 2FA belum cocok. Coba kirim ulang password yang benar.');

        return true;
    }

    protected function registerClientAccount(string $chatId, array $from): TelegramClientAccount
    {
        $account = TelegramClientAccount::firstOrNew(['bot_chat_id' => $chatId]);

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

    protected function findOrRegisterClientAccount(string $chatId, array $from): TelegramClientAccount
    {
        return TelegramClientAccount::where('bot_chat_id', $chatId)->first()
            ?? $this->registerClientAccount($chatId, $from);
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
            '<b>Mulai dari menu dulu.</b>',
            '',
            'Untuk membuat userbot, klik tombol <b>Buat Userbot</b> lalu ikuti instruksi nomor dan OTP dari bot ini.',
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

    protected function requestPhoneMessage(): string
    {
        return implode("\n", [
            '<b>Buat Userbot</b>',
            '',
            'Kirim nomor Telegram yang ingin kamu hubungkan.',
            '',
            'Format wajib pakai kode negara.',
            'Contoh: <code>+6281234567890</code>',
            '',
            'Setelah itu Telegram akan mengirim kode OTP ke akun tersebut. Masukkan OTP lewat tombol web yang bot kirim, bukan lewat chat.',
        ]);
    }

    protected function otpLinkKeyboard(TelegramClientAccount $account): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => 'Input OTP',
                        'url' => $this->otpUrl($account),
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
            '<b>Selamat datang di VixStore AutoShare.</b>',
            '',
            'Tempat kamu mengelola userbot Telegram untuk bantu share promosi jualan ke grup-grup yang sudah kamu daftarkan.',
            '',
            '<b>Apa yang bisa kamu lakukan di sini?</b>',
            '• Membuat userbot dari akun Telegram kamu',
            '• Menyimpan daftar grup target promosi',
            '• Mengirim pesan promosi ke semua grup dengan command share',
            '• Melihat status userbot dan aturan penggunaan',
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
                        'text' => 'Buat Userbot',
                        'callback_data' => 'userbot:create',
                    ],
                    [
                        'text' => 'List Bot',
                        'callback_data' => 'userbot:list',
                    ],
                ],
                [
                    [
                        'text' => 'Tentang Bot',
                        'callback_data' => 'bot:about',
                    ],
                    [
                        'text' => 'Rules',
                        'callback_data' => 'bot:rules',
                    ],
                ],
            ],
        ];
    }
}
