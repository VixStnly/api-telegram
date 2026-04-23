@extends('layouts.app')

@section('page-title', 'Test Message')
@section('page-subtitle', 'Uji engine auto reply langsung dari dashboard')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-6">Form Test Engine</h3>

        <form action="{{ route('auto-reply-test.process') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Managed Device</label>
                <select name="managed_device_id"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
                    <option value="">Pilih device / kosongkan untuk global</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" {{ old('managed_device_id') == $device->id ? 'selected' : '' }}>
                            {{ $device->device_name }} ({{ $device->device_code }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Group Key</label>
                <input type="text"
                       name="group_key"
                       value="{{ old('group_key', '') }}"
                       placeholder="Contoh: cabang_a"
                       class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-0">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Group Name</label>
                <input type="text"
                       name="group_name"
                       value="{{ old('group_name', '') }}"
                       placeholder="Contoh: Grup Cabang A"
                       class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-0">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Sender Key</label>
                <input type="text"
                       name="sender_key"
                       value="{{ old('sender_key', '') }}"
                       placeholder="Contoh: user_001"
                       class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-0">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Sender Name</label>
                <input type="text"
                       name="sender_name"
                       value="{{ old('sender_name', '') }}"
                       placeholder="Contoh: Pelanggan A"
                       class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-0">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">Message Text</label>
                <textarea name="message_text"
                          rows="6"
                          placeholder="Ketik pesan yang ingin diuji..."
                          class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder-slate-500 focus:border-indigo-500 focus:ring-0">{{ old('message_text', '') }}</textarea>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit"
                        class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 font-semibold transition">
                    Process Test
                </button>

                <a href="{{ route('auto-reply-test.index') }}"
                   class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold transition">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
        <h3 class="text-lg font-semibold text-white mb-6">Hasil Engine</h3>

        @if(!is_null($result))
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="rounded-2xl bg-slate-800/70 p-4">
                        <div class="text-sm text-slate-400">Matched</div>
                        <div class="mt-2 text-xl font-bold {{ !empty($result['matched']) ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ !empty($result['matched']) ? 'YES' : 'NO' }}
                        </div>
                    </div>

                    <div class="rounded-2xl bg-slate-800/70 p-4">
                        <div class="text-sm text-slate-400">Replied</div>
                        <div class="mt-2 text-xl font-bold {{ !empty($result['replied']) ? 'text-emerald-400' : 'text-amber-400' }}">
                            {{ !empty($result['replied']) ? 'YES' : 'NO' }}
                        </div>
                    </div>

                    <div class="rounded-2xl bg-slate-800/70 p-4">
                        <div class="text-sm text-slate-400">Rule ID</div>
                        <div class="mt-2 text-xl font-bold text-white">
                            {{ $result['rule_id'] ?? '-' }}
                        </div>
                    </div>

                    <div class="rounded-2xl bg-slate-800/70 p-4">
                        <div class="text-sm text-slate-400">Skip Reason</div>
                        <div class="mt-2 text-lg font-semibold text-slate-200 break-words">
                            {{ $result['skip_reason'] ?? '-' }}
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-800/70 p-5">
                    <div class="text-sm text-slate-400 mb-3">Reply Text</div>
                    <div class="text-slate-100 whitespace-pre-line min-h-[180px]">
                        {{ $result['reply_text'] ?? '-' }}
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-2xl bg-slate-800/50 border border-slate-700 p-6 text-slate-400">
                Belum ada hasil test. Isi form di sebelah kiri lalu klik <span class="text-white font-semibold">Process Test</span>.
            </div>
        @endif
    </div>
</div>
@endsection