@extends('layouts.app')

@section('page-title', 'Edit Managed Device')
@section('page-subtitle', 'Perbarui data device, account, dan group')

@section('content')
<form action="{{ route('managed-devices.update', $device->id) }}" method="POST" class="rounded-3xl bg-slate-900 border border-slate-800 p-6">
    @csrf
    @method('PUT')
    @include('managed-devices._form')

    <div class="mt-8 flex gap-3">
        <button class="rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-3 font-semibold">
            Update
        </button>
        <a href="{{ route('managed-devices.index') }}" class="rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-5 py-3 font-semibold">
            Kembali
        </a>
    </div>
</form>
@endsection