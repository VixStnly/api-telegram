<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Input OTP - VixStore AutoShare</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(20,184,166,0.18),_transparent_32%),linear-gradient(180deg,_#0c0a09_0%,_#111827_52%,_#09090b_100%)] px-4 py-8">
        <section class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-md items-center">
            <div class="w-full overflow-hidden rounded-2xl border border-white/10 bg-white/[0.05] shadow-2xl shadow-black/40 backdrop-blur">
                <div class="border-b border-white/10 px-6 py-6">
                    <div class="inline-flex rounded-full border border-teal-400/30 bg-teal-400/10 px-3 py-1 text-xs font-semibold text-teal-200">
                        VixStore AutoShare
                    </div>
                    <h1 class="mt-5 text-2xl font-bold text-white">Masukkan kode OTP</h1>
                    <p class="mt-3 text-sm leading-6 text-zinc-300">
                        Gunakan kode terbaru yang dikirim Telegram untuk nomor
                        <span class="font-semibold text-white">{{ $account->phone_number }}</span>.
                    </p>
                </div>

                <div class="px-6 py-6">
                    @if ($errors->any())
                        <div class="mb-5 rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('telegram-login.store', ['account' => $account->id, 'token' => $token]) }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="code" class="mb-2 block text-sm font-medium text-zinc-200">Kode OTP</label>
                            <input
                                id="code"
                                name="code"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                autofocus
                                required
                                maxlength="8"
                                class="block w-full rounded-xl border border-white/10 bg-black/30 px-4 py-4 text-center text-2xl font-bold tracking-[0.35em] text-white placeholder-zinc-600 outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-400/20"
                                placeholder="12345"
                            >
                        </div>

                        <button type="submit" class="w-full rounded-xl bg-teal-400 px-4 py-3 font-semibold text-zinc-950 shadow-lg shadow-teal-950/30 transition hover:bg-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200 focus:ring-offset-2 focus:ring-offset-zinc-950">
                            Lanjutkan Login
                        </button>
                    </form>

                    <div class="mt-5 rounded-xl border border-amber-300/20 bg-amber-300/10 px-4 py-3 text-xs leading-5 text-amber-100">
                        Jangan bagikan kode ini ke siapa pun. Halaman ini hanya menyimpan kode ke proses login userbot yang sedang kamu mulai.
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
