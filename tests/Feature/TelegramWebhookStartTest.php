<?php

namespace Tests\Feature;

use App\Models\TelegramClientAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_command_registers_account_and_sends_welcome_message(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 123],
            ]),
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 1,
                'message' => [
                    'message_id' => 10,
                    'text' => '/start',
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                        'username' => 'tester_shop',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('telegram_client_accounts', [
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'bot_username' => 'tester_shop',
            'bot_first_name' => 'Tester',
            'auth_status' => 'awaiting_phone',
        ]);

        $account = TelegramClientAccount::first();
        $this->assertNotNull($account);
        $this->assertStringStartsWith('client_987654321_', $account->session_name);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && $request['chat_id'] === '987654321'
                && str_contains($request['text'], 'Selamat datang di VixStore AutoShare')
                && ($request['reply_markup']['inline_keyboard'][0][0]['text'] ?? null) === 'Buat Userbot';
        });
    }
}
