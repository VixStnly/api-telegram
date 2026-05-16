<?php

namespace App\Http\Controllers;

use App\Models\TelegramClientAccount;
use App\Models\TelegramGroup;
use App\Services\AutoReplyEngine;
use App\Services\PyrogramWorkerService;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
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

        $update = $request->all();
        $callbackQuery = $update['callback_query'] ?? null;

        if ($callbackQuery) {
            $this->handleCallbackQuery($callbackQuery, $telegram);

            return response()->json(['ok' => true]);
        }

        $message = $update['message'] ?? null;

        if (!$message) {
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
            $this->registerClientAccount($chatId, $from);

            $telegram->sendMessage($chatId, $this->welcomeMessage(), [
                'parse_mode' => 'HTML',
                'reply_markup' => $this->welcomeKeyboard(),
            ]);

            return response()->json(['ok' => true]);
        }

        if ($text === '/debug_userbot' && $chatId !== '') {
            $account = TelegramClientAccount::where('bot_chat_id', $chatId)->first();

            if (!$account) {
                $telegram->sendMessage($chatId, 'Belum ada data userbot untuk chat ini.');

                return response()->json(['ok' => true]);
            }

            $telegram->sendMessage($chatId, implode("\n", [
                '<b>Debug Userbot</b>',
                '',
                'Status: <code>' . e($account->auth_status) . '</code>',
                'Nomor: <code>' . e($account->phone_number ?? '-') . '</code>',
                'Session: <code>' . e($account->session_name) . '</code>',
                'Code Hash: <code>' . e($account->phone_code_hash ? 'ada (' . strlen($account->phone_code_hash) . ')' : '-') . '</code>',
                'Pending Session: <code>' . e($account->pending_session_string ? 'ada (' . strlen($account->pending_session_string) . ')' : '-') . '</code>',
                'Error: <code>' . e($account->last_error ?? '-') . '</code>',
            ]), ['parse_mode' => 'HTML']);

            return response()->json(['ok' => true]);
        }

        if ($text === '/debug_worker' && $chatId !== '') {
            $result = $pyrogram->debug();

            $telegram->sendMessage($chatId, implode("\n", [
                '<b>Debug Worker</b>',
                '',
                'OK: <code>' . ($result['ok'] ? 'yes' : 'no') . '</code>',
                'Python: <code>' . e($result['python'] ?? '-') . '</code>',
                'Output: <code>' . e($result['output'] ?: '-') . '</code>',
                'Error: <code>' . e(Str::limit($result['error'] ?: '-', 700)) . '</code>',
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
            'sender_name' => trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')),
            'message_text' => $text,
            'meta' => [
                'source' => 'telegram_bot',
                'telegram_update' => $update,
            ],
        ]);

        if (!empty($result['replied']) && !empty($result['reply_text']) && !empty($chat['id'])) {
            $telegram->sendMessage($chat['id'], $result['reply_text']);
        }

        return response()->json(['ok' => true]);
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

        return false;
    }

    protected function handlePhoneNumber(
        TelegramClientAccount $account,
        string $text,
        TelegramBotService $telegram,
        PyrogramWorkerService $pyrogram
    ): bool {
        $phoneNumber = $this->normalizePhoneNumber($text);

        if (!$phoneNumber) {
            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>Format nomor belum valid.</b>',
                '',
                'Kirim nomor Telegram dengan kode negara.',
                'Contoh: <code>+6281234567890</code>',
            ]), ['parse_mode' => 'HTML']);

            return true;
        }

        $account->update([
            'phone_number' => $phoneNumber,
            'auth_status' => 'sending_code',
            'last_error' => null,
            'last_seen_at' => now(),
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>Nomor diterima.</b>',
            '',
            "Nomor: <code>{$phoneNumber}</code>",
            'Sebentar, sistem sedang meminta kode OTP Telegram...',
        ]), ['parse_mode' => 'HTML']);

        $result = $pyrogram->sendCode($account->fresh());

        if ($result['ok']) {
            $account->fresh()->update([
                'auth_status' => 'awaiting_code',
                'phone_code_hash' => $result['data']['phone_code_hash'] ?? null,
                'pending_session_string' => $result['data']['session_string'] ?? null,
                'session_file' => $result['data']['session_file'] ?? null,
                'last_error' => null,
                'last_seen_at' => now(),
            ]);

            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>Kode OTP sudah dikirim oleh Telegram.</b>',
                '',
                'Silakan kirim kode OTP ke chat ini.',
                'Contoh: <code>12345</code>',
                '',
                'Gunakan kode terbaru dari request ini saja.',
                'Jangan kirim kode ini ke orang lain selain bot ini.',
            ]), ['parse_mode' => 'HTML']);

            return true;
        }

        $account->fresh()->update([
            'auth_status' => 'awaiting_phone',
            'last_error' => $result['error'] ?: $result['output'],
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>Belum bisa mengirim OTP.</b>',
            '',
            'Kemungkinan worker Pyrogram belum siap atau konfigurasi API ID/API HASH belum benar.',
            $this->formatWorkerErrorForTelegram($result),
            'Coba lagi beberapa saat, atau hubungi admin.',
        ]), ['parse_mode' => 'HTML']);

        return true;
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

        $result = $pyrogram->signIn($account, $code);
        $status = $result['data']['status'] ?? null;

        if ($result['ok'] && $status === 'password_required') {
            $account->fresh()->update([
                'auth_status' => 'awaiting_password',
                'last_seen_at' => now(),
            ]);

            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>Akun kamu memakai password 2FA.</b>',
                '',
                'Kirim password 2FA Telegram kamu ke chat ini untuk menyelesaikan login.',
            ]), ['parse_mode' => 'HTML']);

            return true;
        }

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

            $telegram->sendMessage($account->bot_chat_id, implode("\n", [
                '<b>Userbot berhasil dibuat.</b>',
                '',
                'Akun Telegram kamu sudah terhubung.',
                'Langkah berikutnya: kita akan tambahkan menu untuk memasukkan link grup tujuan.',
            ]), ['parse_mode' => 'HTML']);

            return true;
        }

        $account->fresh()->update([
            'auth_status' => 'awaiting_code',
            'last_error' => $result['error'] ?: $result['output'],
        ]);

        $telegram->sendMessage($account->bot_chat_id, implode("\n", [
            '<b>Login belum berhasil.</b>',
            '',
            'Kode OTP mungkin salah atau sudah kedaluwarsa. Kirim ulang kode OTP yang terbaru.',
            $this->formatWorkerErrorForTelegram($result),
        ]), ['parse_mode' => 'HTML']);

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

        if (!$account->exists) {
            $account->session_name = 'client_' . Str::slug($chatId) . '_' . Str::lower(Str::random(8));
            $account->auth_status = 'awaiting_phone';
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
            $phoneNumber = '+62' . substr($phoneNumber, 1);
        } elseif (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+' . ltrim($phoneNumber, '0');
        }

        if (!preg_match('/^\+[1-9]\d{7,14}$/', $phoneNumber)) {
            return null;
        }

        return $phoneNumber;
    }

    protected function formatWorkerErrorForTelegram(array $result): string
    {
        $error = trim((string) ($result['error'] ?: $result['output'] ?: 'Tidak ada detail error.'));
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $error) ?: [])));
        $error = implode("\n", array_slice($lines, -8));
        $error = Str::limit($error, 700);

        return 'Detail: <code>' . e($error) . '</code>';
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
            'Setelah itu Telegram akan mengirim kode OTP ke akun tersebut, lalu kamu kirim kode OTP-nya ke bot ini.',
        ]);
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
