<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Reply Engine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 antialiased">
    <div class="flex min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(16,185,129,0.10),_transparent_30%),linear-gradient(135deg,_#09090b_0%,_#111827_48%,_#0c0a09_100%)]">
        <aside class="hidden w-72 border-r border-white/10 bg-black/30 backdrop-blur md:flex md:flex-col">
            <div class="border-b border-white/10 px-6 py-6">
                <h1 class="text-2xl font-bold text-white">AutoReply Admin</h1>
                <p class="mt-1 text-sm text-zinc-400">Operations dashboard</p>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center rounded-xl px-4 py-3 transition {{ request()->routeIs('dashboard') ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-950/30' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('managed-devices.index') }}"
                   class="flex items-center rounded-xl px-4 py-3 transition {{ request()->routeIs('managed-devices.*') ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-950/30' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <span>Managed Devices</span>
                </a>

                <a href="{{ route('auto-reply-rules.index') }}"
                   class="flex items-center rounded-xl px-4 py-3 transition {{ request()->routeIs('auto-reply-rules.*') ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-950/30' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <span>Auto Reply Rules</span>
                </a>

                <a href="{{ route('auto-reply-logs.index') }}"
                   class="flex items-center rounded-xl px-4 py-3 transition {{ request()->routeIs('auto-reply-logs.*') ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-950/30' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <span>Logs</span>
                </a>
                <a href="{{ route('telegram-users.index') }}"
                   class="flex items-center rounded-xl px-4 py-3 transition {{ request()->routeIs('telegram-users.*') ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-950/30' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
                    <span>Telegram Users</span>
                </a>
                <a href="{{ route('auto-reply-test.index') }}"
   class="flex items-center rounded-xl px-4 py-3 transition {{ request()->routeIs('auto-reply-test.*') ? 'bg-emerald-500 text-zinc-950 shadow-lg shadow-emerald-950/30' : 'text-zinc-300 hover:bg-white/10 hover:text-white' }}">
    <span>Test Message</span>
</a>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="rounded-xl border border-white/10 bg-white/[0.04] p-4">
                    <div class="text-sm text-zinc-400">Login as</div>
                    <div class="mt-1 font-semibold text-white">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-zinc-500">{{ auth()->user()->email }}</div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-xl bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-400">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-h-screen">
            <header class="sticky top-0 z-20 border-b border-white/10 bg-zinc-950/80 backdrop-blur">
                <div class="flex items-center justify-between px-6 py-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">@yield('page-title', 'Dashboard')</h2>
                        <p class="text-sm text-zinc-400">@yield('page-subtitle', 'Manage your auto reply engine')</p>
                    </div>
                    <div class="hidden rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-sm text-zinc-400 sm:block">
                        {{ now()->format('d M Y H:i') }}
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                @if(session('success'))
                    <div class="mb-6 rounded-xl border border-emerald-400/30 bg-emerald-400/10 px-4 py-3 text-emerald-100">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 rounded-xl border border-rose-400/30 bg-rose-400/10 px-4 py-3 text-rose-100">
                        <div class="font-semibold mb-2">Terjadi kesalahan:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
