@extends('layouts.app')

@section('page-title', 'Telegram Users')
@section('page-subtitle', 'Daftar user Telegram yang pernah masuk ke bot dan userbot yang mereka miliki')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="rounded-xl border border-white/10 bg-white/[0.05] p-6 shadow-xl shadow-black/20">
        <div class="text-sm text-zinc-400">Telegram Users</div>
        <div class="mt-2 text-3xl font-bold text-white">{{ $totalUsers }}</div>
        <div class="mt-2 text-sm text-emerald-300">Unique bot chat</div>
    </div>
    <div class="rounded-xl border border-white/10 bg-white/[0.05] p-6 shadow-xl shadow-black/20">
        <div class="text-sm text-zinc-400">Total Userbot</div>
        <div class="mt-2 text-3xl font-bold text-white">{{ $totalUserbots }}</div>
        <div class="mt-2 text-sm text-sky-300">Akun Telegram terdaftar</div>
    </div>
    <div class="rounded-xl border border-white/10 bg-white/[0.05] p-6 shadow-xl shadow-black/20">
        <div class="text-sm text-zinc-400">Authorized</div>
        <div class="mt-2 text-3xl font-bold text-white">{{ $authorizedUserbots }}</div>
        <div class="mt-2 text-sm text-amber-300">Userbot siap dipakai</div>
    </div>
</div>

<div class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <form method="GET" class="flex w-full flex-col gap-3 sm:flex-row md:max-w-xl">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Cari nama, username, chat ID, atau nomor..."
                class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
            >
            <button class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950 transition hover:bg-emerald-400">
                Cari
            </button>
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/10 text-left text-zinc-400">
                    <th class="py-3 pr-4">User Telegram</th>
                    <th class="py-3 pr-4">Chat ID</th>
                    <th class="py-3 pr-4">Userbot</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Last Seen</th>
                    <th class="py-3 pr-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr class="border-b border-white/10">
                        <td class="py-4 pr-4">
                            <div class="font-semibold text-white">{{ $user->bot_first_name ?: 'Telegram User' }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $user->bot_username ? '@'.$user->bot_username : 'username belum ada' }}
                            </div>
                        </td>
                        <td class="py-4 pr-4 text-zinc-300">
                            <code>{{ $user->bot_chat_id }}</code>
                        </td>
                        <td class="py-4 pr-4 text-zinc-300">
                            {{ $user->userbot_count }} akun
                        </td>
                        <td class="py-4 pr-4">
                            <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">
                                {{ $user->authorized_count }} authorized
                            </span>
                        </td>
                        <td class="py-4 pr-4 text-zinc-400">
                            {{ $user->last_seen_at ? \Carbon\Carbon::parse($user->last_seen_at)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="py-4 pr-4">
                            <a href="{{ route('telegram-users.show', $user->bot_chat_id) }}" class="rounded-xl bg-sky-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-sky-400">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-zinc-400">Belum ada user Telegram.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
@endsection
