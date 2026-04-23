@extends('layouts.app')

@section('page-title', 'Detail Managed Device')
@section('page-subtitle', 'Lihat detail device, group, rule, dan log')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-1 rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Informasi Device</h3>
        <div class="space-y-3 text-sm">
            <div><span class="text-slate-400">Nama:</span> <span class="text-white">{{ $device->device_name }}</span></div>
            <div><span class="text-slate-400">Code:</span> <span class="text-white">{{ $device->device_code }}</span></div>
            <div><span class="text-slate-400">Account:</span> <span class="text-white">{{ $device->account_label ?? '-' }}</span></div>
            <div><span class="text-slate-400">Identifier:</span> <span class="text-white">{{ $device->account_identifier ?? '-' }}</span></div>
            <div><span class="text-slate-400">Platform:</span> <span class="text-white">{{ $device->platform ?? '-' }}</span></div>
            <div><span class="text-slate-400">Session:</span> <span class="text-white">{{ $device->session_name ?? '-' }}</span></div>
            <div><span class="text-slate-400">Status:</span> <span class="text-white">{{ strtoupper($device->status) }}</span></div>
            <div><span class="text-slate-400">Last Seen:</span> <span class="text-white">{{ $device->last_seen_at?->format('d/m/Y H:i') ?? '-' }}</span></div>
        </div>
    </div>

    <div class="xl:col-span-1 rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Groups</h3>
        <div class="space-y-3">
            @forelse($device->groups as $group)
                <div class="rounded-2xl bg-slate-800/70 p-4">
                    <div class="text-white font-semibold">{{ $group->group_name ?? '-' }}</div>
                    <div class="text-slate-400 text-sm">{{ $group->group_key }}</div>
                </div>
            @empty
                <div class="text-slate-400">Belum ada group.</div>
            @endforelse
        </div>
    </div>

    <div class="xl:col-span-1 rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Statistik</h3>
        <div class="space-y-3 text-sm">
            <div><span class="text-slate-400">Total Rules:</span> <span class="text-white">{{ $device->rules->count() }}</span></div>
            <div><span class="text-slate-400">Total Logs:</span> <span class="text-white">{{ $device->logs->count() }}</span></div>
            <div><span class="text-slate-400">Active:</span> <span class="text-white">{{ $device->is_active ? 'Yes' : 'No' }}</span></div>
        </div>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <a href="{{ route('managed-devices.edit', $device->id) }}" class="rounded-2xl bg-amber-600 hover:bg-amber-500 text-white px-5 py-3 font-semibold">Edit</a>
    <a href="{{ route('managed-devices.index') }}" class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">Kembali</a>
</div>
@endsection