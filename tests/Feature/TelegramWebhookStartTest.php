<?php

namespace Tests\Feature;

use App\Models\TelegramClientAccount;
use App\Models\TelegramClientGroup;
use App\Models\TelegramAccessCode;
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
                && str_contains(($request['reply_markup']['inline_keyboard'][0][0]['text'] ?? ''), 'Buat Userbot');
        });
    }

    public function test_create_userbot_button_requests_access_code(): void
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
            'auth_status' => 'awaiting_access_code',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Kode Akses Userbot');
        });
    }

    public function test_valid_access_code_unlocks_phone_number_step(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        $accessCode = TelegramAccessCode::create([
            'code' => 'VIX-2026',
            'label' => 'Tester',
            'is_active' => true,
            'max_uses' => 1,
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_access_code',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 25,
                'message' => [
                    'message_id' => 20,
                    'text' => ' vix-2026 ',
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

        $account->refresh();
        $accessCode->refresh();

        $this->assertSame('awaiting_phone', $account->auth_status);
        $this->assertSame(0, $accessCode->used_count);
        $this->assertSame('VIX-2026', $account->meta['access_code']['code'] ?? null);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Kode akses diterima')
                && str_contains($request['text'], 'Contoh: <code>+6281234567890</code>');
        });
    }

    public function test_access_code_accepts_minor_format_differences(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        TelegramAccessCode::create([
            'code' => 'PROMO-1',
            'is_active' => true,
            'max_uses' => 3,
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_access_code',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 28,
                'message' => [
                    'message_id' => 23,
                    'text' => ' promo1 ',
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

        $account->refresh();

        $this->assertSame('awaiting_phone', $account->auth_status);
    }

    public function test_invalid_access_code_does_not_unlock_phone_number_step(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_access_code',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 26,
                'message' => [
                    'message_id' => 21,
                    'text' => 'SALAH',
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

        $account->refresh();

        $this->assertSame('awaiting_access_code', $account->auth_status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Kode akses tidak valid');
        });
    }

    public function test_exhausted_access_code_does_not_unlock_phone_number_step(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        TelegramAccessCode::create([
            'code' => 'FULL-QUOTA',
            'is_active' => true,
            'max_uses' => 1,
            'used_count' => 1,
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_access_code',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 27,
                'message' => [
                    'message_id' => 22,
                    'text' => 'FULL-QUOTA',
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

        $account->refresh();

        $this->assertSame('awaiting_access_code', $account->auth_status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Kode akses tidak valid');
        });
    }

    public function test_start_command_does_not_reset_authorized_userbot(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/*' => Http::response(['ok' => true]),
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281234567890',
            'session_name' => 'client_987654321_test',
            'session_string' => 'saved-session',
            'pending_session_string' => 'legacy-session',
            'auth_status' => 'authorized',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 20,
                'message' => [
                    'message_id' => 1,
                    'text' => '/start',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $account->refresh();

        $this->assertSame('authorized', $account->auth_status);
        $this->assertSame('saved-session', $account->session_string);
        $this->assertSame('legacy-session', $account->pending_session_string);
    }

    public function test_create_userbot_button_creates_new_slot_without_resetting_authorized_userbot(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/*' => Http::response(['ok' => true]),
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281234567890',
            'session_name' => 'client_987654321_test',
            'session_string' => 'saved-session',
            'auth_status' => 'authorized',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 21,
                'callback_query' => [
                    'id' => 'callback-create-authorized',
                    'data' => 'userbot:create',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'message' => [
                        'message_id' => 15,
                        'chat' => [
                            'id' => 987654321,
                            'type' => 'private',
                        ],
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $account->refresh();

        $this->assertSame('authorized', $account->auth_status);
        $this->assertSame('saved-session', $account->session_string);

        $this->assertDatabaseHas('telegram_client_accounts', [
            'bot_chat_id' => '987654321',
            'phone_number' => null,
            'auth_status' => 'awaiting_access_code',
        ]);
    }

    public function test_two_factor_password_is_stored_for_running_login_worker(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/*' => Http::response(['ok' => true]),
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281234567890',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'awaiting_password',
            'pending_login_token' => 'login-token',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 22,
                'message' => [
                    'message_id' => 17,
                    'text' => '  exact 2fa password  ',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $account->refresh();

        $this->assertSame('awaiting_password', $account->auth_status);
        $this->assertSame('  exact 2fa password  ', $account->pending_2fa_password);
        $this->assertNull($account->last_error);
    }

    public function test_debug_userbot_shows_current_login_slot_when_old_idle_slot_exists(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/*' => Http::response(['ok' => true]),
        ]);

        TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_old',
            'auth_status' => 'idle',
            'is_active' => true,
        ]);

        TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+62895613113418',
            'session_name' => 'client_987654321_new',
            'auth_status' => 'sending_code',
            'pending_login_token' => 'debug-token',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 23,
                'message' => [
                    'message_id' => 18,
                    'text' => '/debug_userbot',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Status: <code>sending_code</code>')
                && str_contains($request['text'], 'Nomor: <code>+62895613113418</code>')
                && str_contains($request['text'], 'Session: <code>client_987654321_new</code>');
        });
    }

    public function test_phone_number_goes_to_latest_awaiting_phone_slot_even_with_stale_sending_code(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/sendMessage' => Http::response(['ok' => true]),
        ]);

        Process::fake([
            '*' => Process::result(output: "12345\n"),
        ]);

        TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281111111111',
            'session_name' => 'client_987654321_stale',
            'auth_status' => 'sending_code',
            'pending_login_token' => 'stale-token',
            'is_active' => true,
        ]);

        $awaitingPhone = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'session_name' => 'client_987654321_new',
            'auth_status' => 'awaiting_phone',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 24,
                'message' => [
                    'message_id' => 19,
                    'text' => '+62895613113418',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'chat' => [
                        'id' => 987654321,
                        'type' => 'private',
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $awaitingPhone->refresh();

        $this->assertSame('+62895613113418', $awaitingPhone->phone_number);
        $this->assertSame('sending_code', $awaitingPhone->auth_status);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottesting-token/sendMessage'
                && str_contains($request['text'], 'Memproses permintaan OTP.');
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
                && str_contains(($request['reply_markup']['inline_keyboard'][0][0]['text'] ?? ''), 'Buat Userbot');
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

    public function test_add_group_lists_userbot_groups_and_toggle_selection(): void
    {
        config([
            'services.telegram.bot_token' => 'testing-token',
            'services.telegram.webhook_secret' => 'testing-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/bottesting-token/*' => Http::response(['ok' => true]),
        ]);

        Process::fake([
            '*' => Process::result(output: json_encode([
                'status' => 'ok',
                'groups' => [
                    [
                        'chat_id' => '-1001234567890',
                        'title' => 'Grup Jualan Test',
                        'username' => 'jualan_test',
                        'type' => 'ChatType.SUPERGROUP',
                    ],
                ],
            ])),
        ]);

        $account = TelegramClientAccount::create([
            'bot_chat_id' => '987654321',
            'bot_user_id' => '987654321',
            'phone_number' => '+6281234567890',
            'session_name' => 'client_987654321_test',
            'auth_status' => 'authorized',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 6,
                'callback_query' => [
                    'id' => 'callback-add-group',
                    'data' => 'userbot:add_group:'.$account->id,
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'message' => [
                        'message_id' => 15,
                        'chat' => [
                            'id' => 987654321,
                            'type' => 'private',
                        ],
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('telegram_client_groups', [
            'telegram_client_account_id' => $account->id,
            'chat_id' => '-1001234567890',
            'title' => 'Grup Jualan Test',
            'status' => 'inactive',
        ]);

        $response = $this
            ->withHeader('X-Telegram-Bot-Api-Secret-Token', 'testing-secret')
            ->postJson('/telegram/webhook', [
                'update_id' => 7,
                'callback_query' => [
                    'id' => 'callback-toggle-group',
                    'data' => 'userbot:toggle_group:'.$account->id.':-1001234567890',
                    'from' => [
                        'id' => 987654321,
                        'is_bot' => false,
                        'first_name' => 'Tester',
                    ],
                    'message' => [
                        'message_id' => 16,
                        'chat' => [
                            'id' => 987654321,
                            'type' => 'private',
                        ],
                    ],
                ],
            ]);

        $response->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('telegram_client_groups', [
            'telegram_client_account_id' => $account->id,
            'chat_id' => '-1001234567890',
            'status' => 'active',
        ]);

        $this->assertTrue(
            TelegramClientGroup::where('telegram_client_account_id', $account->id)
                ->where('chat_id', '-1001234567890')
                ->where('status', 'active')
                ->exists()
        );
    }
}
