@extends('layouts.app')

@section('page-title', 'Auto Reply Rules')
@section('page-subtitle', 'Kelola keyword, regex, balasan, cooldown, dan prioritas')

@section('content')
<div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="Cari rule..."
                   class="rounded-2xl border border-slate-700 bg-slate-950 text-slate-100 px-4 py-3 w-80 focus:border-indigo-500 focus:ring-0">
            <button class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">
                Cari
            </button>
        </form>

        <a href="{{ route('auto-reply-rules.create') }}"
           class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 font-semibold">
            + Tambah Rule
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="text-left text-slate-400 border-b border-slate-800">
                    <th class="py-3 pr-4">Name</th>
                    <th class="py-3 pr-4">Device</th>
                    <th class="py-3 pr-4">Match</th>
                    <th class="py-3 pr-4">Pattern</th>
                    <th class="py-3 pr-4">Priority</th>
                    <th class="py-3 pr-4">Cooldown</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $rule)
                    <tr class="border-b border-slate-800/70">
                        <td class="py-4 pr-4 text-white font-semibold">{{ $rule->name }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ $rule->device->device_name ?? 'GLOBAL' }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ strtoupper($rule->match_type) }}</td>
                        <td class="py-4 pr-4 text-slate-400 max-w-xs truncate">{{ $rule->pattern }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ $rule->priority }}</td>
                        <td class="py-4 pr-4 text-slate-300">{{ $rule->cooldown_seconds }}s</td>
                        <td class="py-4 pr-4">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $rule->is_active ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">
                                {{ $rule->is_active ? 'ACTIVE' : 'INACTIVE' }}
                            </span>
                        </td>
                        <td class="py-4 pr-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('auto-reply-rules.show', $rule->id) }}" class="px-3 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold">View</a>
                                <a href="{{ route('auto-reply-rules.edit', $rule->id) }}" class="px-3 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-semibold">Edit</a>
                                <form action="{{ route('auto-reply-rules.destroy', $rule->id) }}" method="POST" onsubmit="return confirm('Hapus rule ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="px-3 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-6 text-center text-slate-400">Belum ada rule.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rules->links() }}
    </div>
</div>
@endsection