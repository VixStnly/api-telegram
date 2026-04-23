<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

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
        return Http::post("{$this->baseUrl}/sendMessage", array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $extra))->json();
    }

    public function setWebhook(string $url, ?string $secretToken = null)
    {
        $payload = ['url' => $url];

        if ($secretToken) {
            $payload['secret_token'] = $secretToken;
        }

        return Http::post("{$this->baseUrl}/setWebhook", $payload)->json();
    }

    public function getMe()
    {
        return Http::get("{$this->baseUrl}/getMe")->json();
    }
}