@php
    $deviceGroups = old('group_keys', isset($device) ? $device->groups->pluck('group_key')->toArray() : ['']);
    $deviceGroupNames = old('group_names', isset($device) ? $device->groups->pluck('group_name')->toArray() : ['']);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Nama Device</label>
        <input type="text" name="device_name" value="{{ old('device_name', $device->device_name ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Label Akun</label>
        <input type="text" name="account_label" value="{{ old('account_label', $device->account_label ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Identifier Akun</label>
        <input type="text" name="account_identifier" value="{{ old('account_identifier', $device->account_identifier ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Platform</label>
        <input type="text" name="platform" value="{{ old('platform', $device->platform ?? 'telegram') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Session Name</label>
        <input type="text" name="session_name" value="{{ old('session_name', $device->session_name ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Session Token</label>
        <input type="text" name="session_token" value="{{ old('session_token', $device->session_token ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Status</label>
        <select name="status" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            <option value="inactive" {{ old('status', $device->status ?? 'inactive') == 'inactive' ? 'selected' : '' }}>Inactive</option>
            <option value="online" {{ old('status', $device->status ?? '') == 'online' ? 'selected' : '' }}>Online</option>
            <option value="offline" {{ old('status', $device->status ?? '') == 'offline' ? 'selected' : '' }}>Offline</option>
        </select>
    </div>

    <div class="flex items-center mt-8">
        <input type="checkbox" name="is_active" value="1"
               {{ old('is_active', isset($device) ? $device->is_active : true) ? 'checked' : '' }}
               class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
        <label class="ml-3 text-slate-300">Active</label>
    </div>
</div>

<div class="mt-8">
    <h3 class="text-lg font-semibold text-white mb-4">Groups</h3>

    <div id="group-wrapper" class="space-y-4">
        @foreach($deviceGroups as $index => $groupKey)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 group-item">
                <input type="text" name="group_keys[]" value="{{ $groupKey }}" placeholder="Group Key"
                       class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
                <input type="text" name="group_names[]" value="{{ $deviceGroupNames[$index] ?? '' }}" placeholder="Group Name"
                       class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            </div>
        @endforeach
    </div>

    <button type="button" onclick="addGroupField()"
            class="mt-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 font-semibold">
        + Tambah Group
    </button>
</div>

<script>
function addGroupField() {
    const wrapper = document.getElementById('group-wrapper');
    const div = document.createElement('div');
    div.className = 'grid grid-cols-1 md:grid-cols-2 gap-4 group-item mt-4';
    div.innerHTML = `
        <input type="text" name="group_keys[]" placeholder="Group Key"
               class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
        <input type="text" name="group_names[]" placeholder="Group Name"
               class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    `;
    wrapper.appendChild(div);
}
</script>