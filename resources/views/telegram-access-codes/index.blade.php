@extends('layouts.app')

@section('page-title', 'Telegram Access Codes')
@section('page-subtitle', 'Kelola kode akses untuk membuat userbot')

@section('content')
<div class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
    <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <form method="GET" class="flex w-full flex-col gap-3 sm:flex-row md:max-w-xl">
            <input
                type="text"
                name="search"
                value="{{ $search }}"
                placeholder="Cari kode, label, atau catatan..."
                class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
            >
            <button class="rounded-xl bg-white/10 px-5 py-3 font-semibold text-white transition hover:bg-white/15">
                Cari
            </button>
        </form>

        <a href="{{ route('telegram-access-codes.create') }}" class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950 transition hover:bg-emerald-400">
            + Buat Kode
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/10 text-left text-zinc-400">
                    <th class="py-3 pr-4">Kode</th>
                    <th class="py-3 pr-4">Label</th>
                    <th class="py-3 pr-4">Sisa Kuota</th>
                    <th class="py-3 pr-4">Expired</th>
                    <th class="py-3 pr-4">Status</th>
                    <th class="py-3 pr-4">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($codes as $code)
                    <tr class="border-b border-white/10 align-top">
                        <td class="py-4 pr-4">
                            <div class="font-semibold text-white"><code>{{ $code->code }}</code></div>
                            <div class="mt-1 max-w-xs truncate text-xs text-zinc-500">{{ $code->notes ?: '-' }}</div>
                        </td>
                        <td class="py-4 pr-4 text-zinc-300">{{ $code->label ?: '-' }}</td>
                        <td class="py-4 pr-4 text-zinc-300">
                            <div class="font-semibold text-white">
                                {{ $code->remainingUses() ?? 'unlimited' }} / {{ $code->max_uses ?: 'unlimited' }}
                            </div>
                            <div class="text-xs text-zinc-500">
                                Terpakai: {{ $code->used_count }}
                            </div>
                            <div class="text-xs text-zinc-500">Last: {{ $code->last_used_at?->format('d/m/Y H:i') ?? '-' }}</div>
                        </td>
                        <td class="py-4 pr-4 text-zinc-300">
                            {{ $code->expires_at?->format('d/m/Y H:i') ?? '-' }}
                        </td>
                        <td class="py-4 pr-4">
                            @if($code->isAvailable())
                                <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-semibold text-emerald-200">AVAILABLE</span>
                            @elseif($code->isQuotaExhausted())
                                <span class="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-semibold text-amber-200">KUOTA HABIS</span>
                            @else
                                <span class="rounded-full bg-rose-400/10 px-3 py-1 text-xs font-semibold text-rose-200">LOCKED</span>
                            @endif
                        </td>
                        <td class="py-4 pr-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('telegram-access-codes.edit', $code) }}" class="rounded-xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-400">Edit</a>
                                <form method="POST" action="{{ route('telegram-access-codes.destroy', $code) }}" onsubmit="return confirm('Hapus kode akses ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-xl bg-rose-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-400">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-zinc-400">Belum ada kode akses.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $codes->links() }}
    </div>
</div>
@endsection
