<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input OTP - VixStore AutoShare</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <main class="min-h-screen flex items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
            <div class="mb-6">
                <div class="text-sm text-indigo-300 font-semibold">VixStore AutoShare</div>
                <h1 class="mt-2 text-2xl font-bold text-white">Masukkan kode OTP</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400">
                    Gunakan kode terbaru yang dikirim Telegram untuk nomor
                    <span class="font-semibold text-slate-200">{{ $account->phone_number }}</span>.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-5 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('telegram-login.store', ['account' => $account->id, 'token' => $token]) }}" class="space-y-5">
                @csrf

                <div>
                    <label for="code" class="block text-sm font-medium text-slate-300 mb-2">Kode OTP</label>
                    <input
                        id="code"
                        name="code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        autofocus
                        required
                        maxlength="8"
                        class="block w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-4 text-center text-2xl font-bold tracking-[0.35em] text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none focus:ring-0"
                        placeholder="12345"
                    >
                </div>

                <button type="submit" class="w-full rounded-2xl bg-indigo-600 px-4 py-3 font-semibold text-white transition hover:bg-indigo-500">
                    Lanjutkan Login
                </button>
            </form>

            <p class="mt-5 text-xs leading-5 text-slate-500">
                Jangan bagikan kode ini ke siapa pun. Halaman ini hanya menyimpan kode ke proses login userbot yang sedang kamu mulai.
            </p>
        </section>
    </main>
</body>
</html>
