@extends('layouts.app')

@section('page-title', 'Detail Telegram User')
@section('page-subtitle', 'Bot dan userbot yang dimiliki user ini')

@section('content')
<div class="mb-6">
    <a href="{{ route('telegram-users.index') }}" class="inline-flex rounded-xl border border-white/10 bg-white/[0.04] px-4 py-2 text-sm font-semibold text-zinc-200 transition hover:bg-white/10">
        Kembali ke Telegram Users
    </a>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-[0.9fr_1.1fr]">
    <section class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
        <div class="text-sm text-zinc-400">Owner</div>
        <h3 class="mt-2 text-2xl font-bold text-white">{{ $owner->bot_first_name ?: 'Telegram User' }}</h3>
        <div class="mt-1 text-sm text-zinc-500">{{ $owner->bot_username ? '@'.$owner->bot_username : 'username belum ada' }}</div>

        <dl class="mt-6 space-y-4 text-sm">
            <div>
                <dt class="text-zinc-500">Bot Chat ID</dt>
                <dd class="mt-1 text-zinc-200"><code>{{ $botChatId }}</code></dd>
            </div>
            <div>
                <dt class="text-zinc-500">Bot User ID</dt>
                <dd class="mt-1 text-zinc-200"><code>{{ $owner->bot_user_id ?? '-' }}</code></dd>
            </div>
            <div>
                <dt class="text-zinc-500">Last Seen</dt>
                <dd class="mt-1 text-zinc-200">{{ $owner->last_seen_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
        </dl>
    </section>

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
            <div class="text-sm text-zinc-400">Userbot</div>
            <div class="mt-2 text-3xl font-bold text-white">{{ $accounts->count() }}</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
            <div class="text-sm text-zinc-400">Grup Aktif</div>
            <div class="mt-2 text-3xl font-bold text-white">{{ $activeGroups }}</div>
            <div class="mt-2 text-sm text-zinc-500">Total grup: {{ $totalGroups }}</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
            <div class="text-sm text-zinc-400">Share</div>
            <div class="mt-2 text-3xl font-bold text-white">{{ $totalShares }}</div>
        </div>
    </section>
</div>

<div class="mt-6 rounded-xl border border-white/10 bg-white/[0.05] p-6">
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-white">Bot yang dimiliki user</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/10 text-left text-zinc-400">
                    <th class="py-3 pr-4">Nomor Telegram</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Grup</th>
                    <th class="py-3 pr-4">Share</th>
                    <th class="py-3 pr-4">Login</th>
                    <th class="py-3 pr-4">Last Error</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                    <tr class="border-b border-white/10 align-top">
                        <td class="py-4 pr-4">
                            <div class="font-semibold text-white">{{ $account->phone_number ?? '-' }}</div>
                            <div class="text-xs text-zinc-500">{{ $account->session_name }}</div>
                        </td>
                        <td class="py-4 pr-4">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $account->auth_status === 'authorized' ? 'bg-emerald-400/10 text-emerald-200' : 'bg-amber-400/10 text-amber-200' }}">
                                {{ $account->auth_status }}
                            </span>
                            <div class="mt-2 text-xs text-zinc-500">{{ $account->is_active ? 'active' : 'inactive' }}</div>
                        </td>
                        <td class="py-4 pr-4 text-zinc-300">
                            {{ $account->active_groups_count }} aktif / {{ $account->groups_count }} total
                        </td>
                        <td class="py-4 pr-4 text-zinc-300">
                            {{ $account->share_messages_count }}
                        </td>
                        <td class="py-4 pr-4 text-zinc-400">
                            {{ $account->last_login_at?->format('d/m/Y H:i') ?? '-' }}
                            <div class="text-xs text-zinc-500">Seen: {{ $account->last_seen_at?->format('d/m/Y H:i') ?? '-' }}</div>
                        </td>
                        <td class="max-w-sm py-4 pr-4 text-zinc-400">
                            <div class="truncate" title="{{ $account->last_error }}">
                                {{ $account->last_error ? \Illuminate\Support\Str::limit($account->last_error, 120) : '-' }}
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
