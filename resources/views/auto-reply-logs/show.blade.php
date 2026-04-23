@extends('layouts.app')

@section('page-title', 'Detail Log')
@section('page-subtitle', 'Lihat hasil lengkap proses auto reply')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Informasi Log</h3>
        <div class="space-y-3 text-sm">
            <div><span class="text-slate-400">Device:</span> <span class="text-white">{{ $log->device->device_name ?? '-' }}</span></div>
            <div><span class="text-slate-400">Group:</span> <span class="text-white">{{ $log->group_name ?? $log->group_key ?? '-' }}</span></div>
            <div><span class="text-slate-400">Sender:</span> <span class="text-white">{{ $log->sender_name ?? $log->sender_key ?? '-' }}</span></div>
            <div><span class="text-slate-400">Rule:</span> <span class="text-white">{{ $log->rule->name ?? '-' }}</span></div>
            <div><span class="text-slate-400">Matched:</span> <span class="text-white">{{ $log->is_matched ? 'Yes' : 'No' }}</span></div>
            <div><span class="text-slate-400">Replied:</span> <span class="text-white">{{ $log->is_replied ? 'Yes' : 'No' }}</span></div>
            <div><span class="text-slate-400">Skip Reason:</span> <span class="text-white">{{ $log->skip_reason ?? '-' }}</span></div>
            <div><span class="text-slate-400">Processed At:</span> <span class="text-white">{{ $log->processed_at?->format('d/m/Y H:i:s') ?? '-' }}</span></div>
        </div>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Isi Pesan</h3>
        <div class="rounded-2xl bg-slate-800/70 p-4 text-slate-200 whitespace-pre-line min-h-[120px]">
            {{ $log->message_text }}
        </div>

        <h3 class="text-lg font-semibold text-white mt-6 mb-4">Reply Text</h3>
        <div class="rounded-2xl bg-slate-800/70 p-4 text-slate-200 whitespace-pre-line min-h-[120px]">
            {{ $log->reply_text ?? '-' }}
        </div>
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('auto-reply-logs.index') }}" class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">
        Kembali
    </a>
</div>
@endsection