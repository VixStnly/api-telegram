<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Reply Engine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen flex bg-slate-950">
        <aside class="w-72 bg-slate-900/95 border-r border-slate-800 hidden md:flex md:flex-col">
            <div class="px-6 py-6 border-b border-slate-800">
                <h1 class="text-2xl font-bold tracking-wide text-white">AutoReply Admin</h1>
                <p class="text-sm text-slate-400 mt-1">Dark Modern Dashboard</p>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2">
                <a href="{{ route('dashboard') }}"
                   class="flex items-center px-4 py-3 rounded-2xl transition {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('managed-devices.index') }}"
                   class="flex items-center px-4 py-3 rounded-2xl transition {{ request()->routeIs('managed-devices.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <span>Managed Devices</span>
                </a>

                <a href="{{ route('auto-reply-rules.index') }}"
                   class="flex items-center px-4 py-3 rounded-2xl transition {{ request()->routeIs('auto-reply-rules.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <span>Auto Reply Rules</span>
                </a>

                <a href="{{ route('auto-reply-logs.index') }}"
                   class="flex items-center px-4 py-3 rounded-2xl transition {{ request()->routeIs('auto-reply-logs.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <span>Logs</span>
                </a>
                <a href="{{ route('auto-reply-test.index') }}"
   class="flex items-center px-4 py-3 rounded-2xl transition {{ request()->routeIs('auto-reply-test.*') ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-900/40' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
    <span>Test Message</span>
</a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <div class="rounded-2xl bg-slate-800/80 p-4">
                    <div class="text-sm text-slate-400">Login as</div>
                    <div class="font-semibold text-white mt-1">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-slate-500">{{ auth()->user()->email }}</div>

                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button type="submit"
                                class="w-full rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500 transition">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-h-screen">
            <header class="sticky top-0 z-20 bg-slate-950/90 backdrop-blur border-b border-slate-800">
                <div class="px-6 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-white">@yield('page-title', 'Dashboard')</h2>
                        <p class="text-sm text-slate-400">@yield('page-subtitle', 'Manage your auto reply engine')</p>
                    </div>
                    <div class="text-sm text-slate-400">
                        {{ now()->format('d M Y H:i') }}
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6">
                @if(session('success'))
                    <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-emerald-300">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-rose-300">
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