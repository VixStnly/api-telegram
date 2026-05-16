<?php

use App\Services\TelegramBotService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:me', function (TelegramBotService $telegram) {
    $this->line(json_encode($telegram->getMe(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Show the configured Telegram bot profile');

Artisan::command('telegram:set-webhook', function (TelegramBotService $telegram) {
    $url = config('services.telegram.webhook_url');
    $secret = config('services.telegram.webhook_secret');

    if (empty($url)) {
        $this->error('TELEGRAM_WEBHOOK_URL is empty in .env');

        return 1;
    }

    $result = $telegram->setWebhook($url, $secret);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return !empty($result['ok']) ? 0 : 1;
})->purpose('Register TELEGRAM_WEBHOOK_URL as the Telegram bot webhook');

Artisan::command('telegram:webhook-info', function (TelegramBotService $telegram) {
    $this->line(json_encode($telegram->getWebhookInfo(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
})->purpose('Show the current Telegram bot webhook info');
