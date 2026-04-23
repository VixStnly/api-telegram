@extends('layouts.app')

@section('page-title', 'Auto Reply Logs')
@section('page-subtitle', 'Pantau semua proses pesan dan hasil matching')

@section('content')
<div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <select name="managed_device_id" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            <option value="">Semua Device</option>
            @foreach($devices as $device)
                <option value="{{ $device->id }}" {{ request('managed_device_id') == $device->id ? 'selected' : '' }}>
                    {{ $device->device_name }}
                </option>
            @endforeach
        </select>

        <input type="text" name="group_key" value="{{ request('group_key') }}" placeholder="Group Key"
               class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">

        <select name="is_matched" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            <option value="">Matched?</option>
            <option value="1" {{ request('is_matched') === '1' ? 'selected' : '' }}>Yes</option>
            <option value="0" {{ request('is_matched') === '0' ? 'selected' : '' }}>No</option>
        </select>

        <select name="is_replied" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            <option value="">Replied?</option>
            <option value="1" {{ request('is_replied') === '1' ? 'selected' : '' }}>Yes</option>
            <option value="0" {{ request('is_replied') === '0' ? 'selected' : '' }}>No</option>
        </select>

        <div class="md:col-span-4 flex gap-3">
            <button class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 font-semibold">
                Filter
            </button>
            <a href="{{ route('auto-reply-logs.index') }}" class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">
                Reset
            </a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-800">
                    <th class="py-3 pr-4">Waktu</th>
                    <th class="py-3 pr-4">Device</th>
                    <th class="py-3 pr-4">Group</th>
                    <th class="py-3 pr-4">Sender</th>
                    <th class="py-3 pr-4">Message</th>
                    <th class="py-3 pr-4">Rule</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-b border-slate-800/70">
                        <td class="py-4 pr-4 text-slate-300">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-4 pr-4 text-white">{{ $log->device->device_name ?? '-' }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ $log->group_name ?? $log->group_key ?? '-' }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ $log->sender_name ?? $log->sender_key ?? '-' }}</td>
                        <td class="py-4 pr-4 text-slate-400 max-w-xs truncate">{{ $log->message_text }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ $log->rule->name ?? '-' }}</td>
                        <td class="py-4 pr-4">
                            @if($log->is_replied)
                                <span class="px-3 py-1 rounded-full text-xs bg-emerald-500/20 text-emerald-300">REPLIED</span>
                            @elseif($log->is_matched)
                                <span class="px-3 py-1 rounded-full text-xs bg-amber-500/20 text-amber-300">MATCHED</span>
                            @else
                                <span class="px-3 py-1 rounded-full text-xs bg-slate-700 text-slate-300">{{ $log->skip_reason ?? 'SKIPPED' }}</span>
                            @endif
                        </td>
                        <td class="py-4 pr-4">
                            <a href="{{ route('auto-reply-logs.show', $log->id) }}" class="px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-slate-400">Belum ada log.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $logs->links() }}
    </div>
</div>
@endsection