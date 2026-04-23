@extends('layouts.app')

@section('page-title', 'Detail Rule')
@section('page-subtitle', 'Lihat detail rule auto reply')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Informasi Rule</h3>
        <div class="space-y-3 text-sm">
            <div><span class="text-slate-400">Nama:</span> <span class="text-white">{{ $rule->name }}</span></div>
            <div><span class="text-slate-400">Device:</span> <span class="text-white">{{ $rule->device->device_name ?? 'GLOBAL' }}</span></div>
            <div><span class="text-slate-400">Match Type:</span> <span class="text-white">{{ strtoupper($rule->match_type) }}</span></div>
            <div><span class="text-slate-400">Pattern:</span> <span class="text-white">{{ $rule->pattern }}</span></div>
            <div><span class="text-slate-400">Priority:</span> <span class="text-white">{{ $rule->priority }}</span></div>
            <div><span class="text-slate-400">Cooldown:</span> <span class="text-white">{{ $rule->cooldown_seconds }}s</span></div>
            <div><span class="text-slate-400">Case Sensitive:</span> <span class="text-white">{{ $rule->case_sensitive ? 'Yes' : 'No' }}</span></div>
            <div><span class="text-slate-400">Status:</span> <span class="text-white">{{ $rule->is_active ? 'Active' : 'Inactive' }}</span></div>
        </div>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Reply Text</h3>
        <div class="rounded-2xl bg-slate-800/70 p-4 text-slate-200 whitespace-pre-line">{{ $rule->reply_text }}</div>

        <h4 class="text-md font-semibold text-white mt-6 mb-3">Allowed Groups</h4>
        <div class="space-y-2">
            @forelse($rule->groups as $group)
                <div class="rounded-xl bg-slate-800/70 p-3 text-slate-300">{{ $group->group_key }}</div>
            @empty
                <div class="text-slate-400">Semua grup diperbolehkan.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 flex gap-3">
    <a href="{{ route('auto-reply-rules.edit', $rule->id) }}" class="rounded-2xl bg-amber-600 hover:bg-amber-500 text-white px-5 py-3 font-semibold">Edit</a>
    <a href="{{ route('auto-reply-rules.index') }}" class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">Kembali</a>
</div>
@endsection