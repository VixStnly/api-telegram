<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - AutoReply Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl overflow-hidden">
                <div class="px-8 pt-8 pb-6 text-center border-b border-slate-800">
                    <h1 class="text-3xl font-bold text-white tracking-wide">AutoReply Admin</h1>
                    <p class="mt-2 text-sm text-slate-400">
                        Login untuk mengelola device, rules, dan logs
                    </p>
                </div>

                <div class="px-8 py-8">
                    @if (session('status'))
                        <div class="mb-5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                            <div class="font-semibold mb-2">Terjadi kesalahan:</div>
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-medium text-slate-300 mb-2">
                                Email
                            </label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                class="block w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-0"
                                placeholder="Masukkan email"
                            >
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-300 mb-2">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="block w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-0"
                                placeholder="Masukkan password"
                            >
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                                <input
                                    id="remember_me"
                                    type="checkbox"
                                    name="remember"
                                    class="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span class="ml-2 text-sm text-slate-400">Remember me</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a
                                    href="{{ route('password.request') }}"
                                    class="text-sm text-indigo-400 hover:text-indigo-300 transition"
                                >
                                    Lupa password?
                                </a>
                            @endif
                        </div>

                        <button
                            type="submit"
                            class="w-full rounded-2xl bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-500 transition"
                        >
                            Login
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-6 text-center text-xs text-slate-500">
                © {{ date('Y') }} AutoReply Engine Dashboard
            </div>
        </div>
    </div>
</body>
</html>