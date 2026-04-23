@extends('layouts.app')

@section('page-title', 'Managed Devices')
@section('page-subtitle', 'Kelola device, akun, session, dan grup')

@section('content')
<div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Cari device..."
                   class="rounded-2xl border border-slate-700 bg-slate-950 text-slate-100 px-4 py-3 w-80 focus:border-indigo-500 focus:ring-0">
            <button class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">
                Cari
            </button>
        </form>

        <a href="{{ route('managed-devices.create') }}"
           class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 font-semibold">
            + Tambah Device
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-800">
                    <th class="py-3 pr-4">Nama Device</th>
                    <th class="py-3 pr-4">Code</th>
                    <th class="py-3 pr-4">Account</th>
                    <th class="py-3 pr-4">Platform</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Groups</th>
                    <th class="py-3 pr-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($devices as $device)
                    <tr class="border-b border-slate-800/70">
                        <td class="py-4 pr-4">
                            <div class="font-semibold text-white">{{ $device->device_name }}</div>
                            <div class="text-xs text-slate-500">Last seen: {{ $device->last_seen_at?->format('d/m/Y H:i') ?? '-' }}</div>
                        </td>
                        <td class="py-4 pr-4 text-slate-300">{{ $device->device_code }}</td>
                        <td class="py-4 pr-4 text-slate-300">
                            <div>{{ $device->account_label ?? '-' }}</div>
                            <div class="text-xs text-slate-500">{{ $device->account_identifier ?? '-' }}</div>
                        </td>
                        <td class="py-4 pr-4 text-slate-300">{{ $device->platform ?? '-' }}</td>
                        <td class="py-4 pr-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $device->status === 'online' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700 text-slate-300' }}">
                                {{ strtoupper($device->status) }}
                            </span>
                        </td>
                        <td class="py-4 pr-4 text-slate-300">
                            {{ $device->groups->count() }}
                        </td>
                        <td class="py-4 pr-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('managed-devices.show', $device->id) }}" class="px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold">View</a>
                                <a href="{{ route('managed-devices.edit', $device->id) }}" class="px-3 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold">Edit</a>
                                <form action="{{ route('managed-devices.destroy', $device->id) }}" method="POST" onsubmit="return confirm('Hapus device ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-6 text-center text-slate-400">Belum ada managed device.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $devices->links() }}
    </div>
</div>
@endsection