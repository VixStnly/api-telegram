<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected string $baseUrl;

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$token}";
    }

    public function sendMessage($chatId, $text, array $extra = [])
    {
        return $this->post('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra));
    }

    public function editMessageText($chatId, $messageId, $text, array $extra = [])
    {
        return $this->post('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
        ], $extra));
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, array $extra = [])
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        return $this->post('answerCallbackQuery', array_merge($payload, $extra));
    }

    public function setWebhook(string $url, ?string $secretToken = null)
    {
        $payload = ['url' => $url];

        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }

        return $this->post('setWebhook', $payload);
    }

    public function getWebhookInfo()
    {
        return Http::get("{$this->baseUrl}/getWebhookInfo")->json();
    }

    public function getMe()
    {
        return Http::get("{$this->baseUrl}/getMe")->json();
    }

    protected function post(string $method, array $payload): array
    {
        try {
            return Http::post("{$this->baseUrl}/{$method}", $payload)->json() ?? [
                'ok' => false,
                'description' => 'Telegram returned an empty response.',
            ];
        } catch (\Throwable $e) {
            Log::error('Telegram API request failed', [
                'method' => $method,
                'message' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'description' => $e->getMessage(),
            ];
        }
    }
}
