@extends('layouts.app')

@section('page-title', 'Dashboard')
@section('page-subtitle', 'Overview sistem auto reply dan device engine')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6 shadow-xl">
        <div class="text-slate-400 text-sm">Total Devices</div>
        <div class="text-3xl font-bold text-white mt-2">{{ $totalDevices }}</div>
        <div class="text-sm text-emerald-400 mt-2">Active: {{ $activeDevices }}</div>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6 shadow-xl">
        <div class="text-slate-400 text-sm">Online Devices</div>
        <div class="text-3xl font-bold text-white mt-2">{{ $onlineDevices }}</div>
        <div class="text-sm text-sky-400 mt-2">Realtime device status</div>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6 shadow-xl">
        <div class="text-slate-400 text-sm">Auto Reply Rules</div>
        <div class="text-3xl font-bold text-white mt-2">{{ $totalRules }}</div>
        <div class="text-sm text-indigo-400 mt-2">Active: {{ $activeRules }}</div>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6 shadow-xl">
        <div class="text-slate-400 text-sm">Logs Processed</div>
        <div class="text-3xl font-bold text-white mt-2">{{ $totalLogs }}</div>
        <div class="text-sm text-amber-400 mt-2">Matched: {{ $matchedLogs }} | Replied: {{ $repliedLogs }}</div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
    <a href="{{ route('managed-devices.index') }}" class="rounded-3xl bg-gradient-to-br from-indigo-600 to-indigo-800 p-6 shadow-2xl hover:scale-[1.01] transition">
        <div class="text-white/80 text-sm">Quick Action</div>
        <div class="text-white text-2xl font-bold mt-2">Manage Devices</div>
        <p class="text-indigo-100 mt-3">Atur device, akun, session, dan grup yang terhubung.</p>
    </a>

    <a href="{{ route('auto-reply-rules.index') }}" class="rounded-3xl bg-gradient-to-br from-emerald-600 to-emerald-800 p-6 shadow-2xl hover:scale-[1.01] transition">
        <div class="text-white/80 text-sm">Quick Action</div>
        <div class="text-white text-2xl font-bold mt-2">Manage Rules</div>
        <p class="text-emerald-100 mt-3">Buat keyword, pattern, prioritas, cooldown, dan balasan.</p>
    </a>

    <a href="{{ route('auto-reply-logs.index') }}" class="rounded-3xl bg-gradient-to-br from-amber-600 to-orange-700 p-6 shadow-2xl hover:scale-[1.01] transition">
        <div class="text-white/80 text-sm">Quick Action</div>
        <div class="text-white text-2xl font-bold mt-2">View Logs</div>
        <p class="text-amber-100 mt-3">Pantau pesan masuk, rule yang match, dan hasil reply.</p>
    </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Latest Devices</h3>
            <a href="{{ route('managed-devices.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">Lihat semua</a>
        </div>
        <div class="space-y-3">
            @forelse($latestDevices as $device)
                <div class="rounded-2xl bg-slate-800/70 p-4 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-white">{{ $device->device_name }}</div>
                        <div class="text-sm text-slate-400">{{ $device->device_code }} - {{ $device->account_label }}</div>
                    </div>
                    <div>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $device->status === 'online' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">
                            {{ strtoupper($device->status) }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="text-slate-400">Belum ada device.</div>
            @endforelse
        </div>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-white">Latest Rules</h3>
            <a href="{{ route('auto-reply-rules.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">Lihat semua</a>
        </div>
        <div class="space-y-3">
            @forelse($latestRules as $rule)
                <div class="rounded-2xl bg-slate-800/70 p-4">
                    <div class="flex items-center justify-between">
                        <div class="font-semibold text-white">{{ $rule->name }}</div>
                        <div class="text-xs text-slate-400">Priority: {{ $rule->priority }}</div>
                    </div>
                    <div class="text-sm text-slate-400 mt-1">
                        {{ strtoupper($rule->match_type) }} - {{ $rule->pattern }}
                    </div>
                    <div class="text-xs mt-2 {{ $rule->is_active ? 'text-emerald-400' : 'text-rose-400' }}">
                        {{ $rule->is_active ? 'Active' : 'Inactive' }}
                    </div>
                </div>
            @empty
                <div class="text-slate-400">Belum ada rule.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="rounded-3xl bg-slate-900 border border-slate-800 p-6 mt-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-white">Latest Logs</h3>
        <a href="{{ route('auto-reply-logs.index') }}" class="text-sm text-indigo-400 hover:text-indigo-300">Lihat semua</a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-800">
                    <th class="py-3 pr-4">Device</th>
                    <th class="py-3 pr-4">Group</th>
                    <th class="py-3 pr-4">Message</th>
                    <th class="py-3 pr-4">Rule</th>
                    <th class="py-3 pr-4">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($latestLogs as $log)
                    <tr class="border-b border-slate-800/70">
                        <td class="py-3 pr-4 text-white">{{ $log->device->device_name ?? '-' }}</td>
                        <td class="py-3 pr-4 text-slate-300">{{ $log->group_name ?? $log->group_key ?? '-' }}</td>
                        <td class="py-3 pr-4 text-slate-400 max-w-xs truncate">{{ $log->message_text }}</td>
                        <td class="py-3 pr-4 text-slate-300">{{ $log->rule->name ?? '-' }}</td>
                        <td class="py-3 pr-4">
                            @if($log->is_replied)
                                <span class="px-3 py-1 rounded-full text-xs bg-emerald-500/20 text-emerald-300">REPLIED</span>
                            @elseif($log->is_matched)
                                <span class="px-3 py-1 rounded-full text-xs bg-amber-500/20 text-amber-300">MATCHED</span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs bg-slate-700 text-slate-300">SKIPPED</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-slate-400">Belum ada log.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection