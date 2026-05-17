@extends('layouts.app')

@section('page-title', 'Edit Access Code')
@section('page-subtitle', 'Perbarui kode akses userbot')

@section('content')
<form method="POST" action="{{ route('telegram-access-codes.update', $accessCode) }}" class="rounded-xl border border-white/10 bg-white/[0.05] p-6">
    @csrf
    @method('PUT')
    @include('telegram-access-codes._form')

    <div class="mt-8 flex flex-wrap gap-3">
        <button class="rounded-xl bg-emerald-500 px-5 py-3 font-semibold text-zinc-950 transition hover:bg-emerald-400">
            Update
        </button>
        <a href="{{ route('telegram-access-codes.index') }}" class="rounded-xl bg-white/10 px-5 py-3 font-semibold text-white transition hover:bg-white/15">
            Kembali
        </a>
    </div>
</form>
@endsection
