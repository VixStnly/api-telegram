<?php

namespace App\Http\Controllers;

use App\Models\TelegramGroup;
use App\Services\AutoReplyEngine;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;

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
        $text = $message['text'] ?? '';

        if (in_array(($chat['type'] ?? ''), ['group', 'supergroup', 'channel'])) {
            TelegramGroup::updateOrCreate(
                ['chat_id' => (string) ($chat['id'] ?? '')],
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

        $result = $engine->process([
            'managed_device_id' => null,
            'group_key' => (string) ($chat['id'] ?? ''),
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
}