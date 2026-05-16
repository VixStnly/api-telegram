<?php

namespace Tests\Feature;

use App\Models\TelegramClientAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
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
            'auth_status' => 'idle',
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

    public function test_create_userbot_button_requests_phone_number(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/*' => Http::response(['ok' => true]),
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 2,
                'callback_query' => [
                    'id' => 'callback-1',
                    'data' => 'userbot:create',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                        'username' => 'tester_shop',
                    ],
                    'message' => [
                        'message_id' => 11,
                        'chat' => [
                            'id' => 987654321,
                            'type' => 'private',
                        ],
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('telegram_client_accounts', [
            'bot_chat_id' => '987654321',
            'auth_status' => 'awaiting_phone',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Kirim nomor Telegram');
        });
    }

    public function test_phone_number_is_saved_and_otp_is_requested(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        Process::fake([
            '*' => Process::result(output: "code_sent\n"),
        ]);

        TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_phone',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 3,
                'message' => [
                    'message_id' => 12,
                    'text' => '081234567890',
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('telegram_client_accounts', [
            'bot_chat_id' => '987654321',
            'phone_number' => '+6281234567890',
            'auth_status' => 'sending_code',
        ]);
    }

    public function test_phone_number_without_create_button_is_guided_to_menu(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        Process::fake();

        TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'idle',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 4,
                'message' => [
                    'message_id' => 13,
                    'text' => '+6281234567890',
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('telegram_client_accounts', [
            'bot_chat_id' => '987654321',
            'phone_number' => '+6281234567890',
        ]);

        Process::assertNothingRan();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Mulai dari menu dulu')
                && ($request['reply_markup']['inline_keyboard'][0][0]['text'] ?? null) === 'Buat Userbot';
        });
    }

    public function test_same_owner_can_retry_existing_phone_number(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        Process::fake([
            '*' => Process::result(output: "code_sent\n"),
        ]);

        TelegramClientAccount::create([
            'bot_chat_id' => 'old-chat',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281234567890',
            'session_name' => 'client_old_test',
            'auth_status' => 'awaiting_phone',
            'is_active' => true,
        ]);

        TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_phone',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 5,
                'message' => [
                    'message_id' => 14,
                    'text' => '+6281234567890',
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('telegram_client_accounts', [
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281234567890',
            'auth_status' => 'sending_code',
        ]);

        $this->assertDatabaseMissing('telegram_client_accounts', [
            'bot_chat_id' => 'old-chat',
            'phone_number' => '+6281234567890',
        ]);
    }
}
