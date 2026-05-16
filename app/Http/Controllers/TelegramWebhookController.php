<?php

namespace App\Http\Controllers;

use App\Models\TelegramClientAccount;
use App\Models\TelegramGroup;
use App\Services\AutoReplyEngine;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramBotService $telegram, AutoReplyEngine $engine)
    {
        $secret = config('services.telegram.webhook_secret');
        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret && $headerSecret !== $secret) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid secret token',
            ], 403);
        }

        $update = $request->all();
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
            ]);

            return response()->json(['ok' => true]);
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

    protected function welcomeMessage(): string
    {
        return implode("\n", [
            '<b>Selamat datang di AutoShare Telegram.</b>',
            '',
            'Bot ini akan membantu kamu menyiapkan akun Telegram untuk share promosi ke daftar grup jualan milikmu.',
            '',
            'Untuk tahap test, fitur login nomor dan share grup sedang disiapkan. Kirim pesan apa saja ke bot ini untuk memastikan webhook sudah aktif.',
        ]);
    }
}
