<?php

namespace App\Http\Controllers;

use App\Models\TelegramClientAccount;
use Illuminate\Http\Request;

class TelegramLoginController extends Controller
{
    public function show(Request $request, TelegramClientAccount $account)
    {
        if (!$this->isValidRequest($request, $account)) {
            abort(404);
        }

        return view('telegram-login.otp', [
            'account' => $account,
            'token' => $request->query('token'),
        ]);
    }

    public function store(Request $request, TelegramClientAccount $account)
    {
        if (!$this->isValidRequest($request, $account)) {
            abort(404);
        }

        $validated = $request->validate([
            'code' => ['required', 'regex:/^\d{4,8}$/'],
        ], [
            'code.required' => 'Kode OTP wajib diisi.',
            'code.regex' => 'Kode OTP hanya boleh angka 4-8 digit.',
        ]);

        $account->update([
            'pending_otp_code' => $validated['code'],
            'last_seen_at' => now(),
        ]);

        return view('telegram-login.submitted');
    }

    protected function isValidRequest(Request $request, TelegramClientAccount $account): bool
    {
        $token = (string) $request->query('token');

        return $token !== ''
            && hash_equals((string) $account->pending_login_token, $token)
            && $account->auth_status === 'awaiting_code';
    }
}
