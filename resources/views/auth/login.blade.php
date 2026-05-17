<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - AutoReply Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <main class="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.16),_transparent_34%),linear-gradient(135deg,_#09090b_0%,_#111827_46%,_#111111_100%)] px-4 py-8">
        <div class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-6xl items-center justify-center">
            <section class="grid w-full overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] shadow-2xl shadow-black/40 backdrop-blur lg:grid-cols-[1.1fr_0.9fr]">
                <div class="hidden min-h-[560px] flex-col justify-between border-r border-white/10 p-10 lg:flex">
                    <div>
                        <div class="inline-flex items-center rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-sm font-semibold text-emerald-200">
                            AutoReply Engine
                        </div>
                        <h1 class="mt-8 max-w-xl text-4xl font-bold leading-tight text-white">
                            Dashboard operasional untuk bot Telegram kamu.
                        </h1>
                        <p class="mt-4 max-w-lg text-base leading-7 text-zinc-300">
                            Kelola device, rules, dan log balasan dari satu panel yang ringan untuk dipakai setiap hari.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                            <div class="text-sm text-zinc-400">Rules</div>
                            <div class="mt-1 text-lg font-semibold text-white">Cepat</div>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                            <div class="text-sm text-zinc-400">Logs</div>
                            <div class="mt-1 text-lg font-semibold text-white">Rapi</div>
                        </div>
                        <div class="rounded-xl border border-white/10 bg-black/20 p-4">
                            <div class="text-sm text-zinc-400">Device</div>
                            <div class="mt-1 text-lg font-semibold text-white">Aktif</div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center p-6 sm:p-10">
                    <div class="w-full max-w-md">
                        <div class="mb-8 lg:hidden">
                            <div class="text-sm font-semibold text-emerald-300">AutoReply Engine</div>
                            <h1 class="mt-3 text-3xl font-bold text-white">Masuk dashboard</h1>
                        </div>

                        <div class="mb-8 hidden lg:block">
                            <h2 class="text-2xl font-bold text-white">Masuk dashboard</h2>
                            <p class="mt-2 text-sm text-zinc-400">Gunakan akun admin untuk lanjut.</p>
                        </div>

                        @if (session('status'))
                            <div class="mb-5 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-sm text-emerald-100">
                                {{ session('status') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-5 rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-sm text-rose-100">
                                <div class="font-semibold">Login belum berhasil</div>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="space-y-5">
                            @csrf

                            <div>
                                <label for="email" class="mb-2 block text-sm font-medium text-zinc-200">Email</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    class="block w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                    placeholder="admin@email.com"
                                >
                            </div>

                            <div>
                                <label for="password" class="mb-2 block text-sm font-medium text-zinc-200">Password</label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    class="block w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
                                    placeholder="Masukkan password"
                                >
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <label for="remember_me" class="inline-flex cursor-pointer items-center">
                                    <input
                                        id="remember_me"
                                        type="checkbox"
                                        name="remember"
                                        class="rounded border-white/20 bg-black/30 text-emerald-500 focus:ring-emerald-500"
                                    >
                                    <span class="ml-2 text-sm text-zinc-400">Ingat saya</span>
                                </label>

                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-emerald-300 transition hover:text-emerald-200">
                                        Lupa password?
                                    </a>
                                @endif
                            </div>

                            <button
                                type="submit"
                                class="w-full rounded-xl bg-emerald-500 px-4 py-3 font-semibold text-zinc-950 shadow-lg shadow-emerald-950/30 transition hover:bg-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:ring-offset-2 focus:ring-offset-zinc-950"
                            >
                                Login
                            </button>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
