@php
    $ruleGroups = old('group_keys', isset($rule) ? $rule->groups->pluck('group_key')->toArray() : ['']);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Nama Rule</label>
        <input type="text" name="name" value="{{ old('name', $rule->name ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Managed Device</label>
        <select name="managed_device_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            <option value="">GLOBAL</option>
            @foreach($devices as $device)
                <option value="{{ $device->id }}" {{ old('managed_device_id', $rule->managed_device_id ?? '') == $device->id ? 'selected' : '' }}>
                    {{ $device->device_name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Match Type</label>
        <select name="match_type" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            <option value="contains" {{ old('match_type', $rule->match_type ?? 'contains') == 'contains' ? 'selected' : '' }}>Contains</option>
            <option value="exact" {{ old('match_type', $rule->match_type ?? '') == 'exact' ? 'selected' : '' }}>Exact</option>
            <option value="regex" {{ old('match_type', $rule->match_type ?? '') == 'regex' ? 'selected' : '' }}>Regex</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Pattern</label>
        <input type="text" name="pattern" value="{{ old('pattern', $rule->pattern ?? '') }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Priority</label>
        <input type="number" name="priority" value="{{ old('priority', $rule->priority ?? 100) }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-300 mb-2">Cooldown Seconds</label>
        <input type="number" name="cooldown_seconds" value="{{ old('cooldown_seconds', $rule->cooldown_seconds ?? 0) }}"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    </div>

    <div class="flex items-center">
        <input type="checkbox" name="case_sensitive" value="1"
               {{ old('case_sensitive', isset($rule) ? $rule->case_sensitive : false) ? 'checked' : '' }}
               class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
        <label class="ml-3 text-slate-300">Case Sensitive</label>
    </div>

    <div class="flex items-center">
        <input type="checkbox" name="is_active" value="1"
               {{ old('is_active', isset($rule) ? $rule->is_active : true) ? 'checked' : '' }}
               class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-indigo-500">
        <label class="ml-3 text-slate-300">Active</label>
    </div>
</div>

<div class="mt-6">
    <label class="block text-sm font-medium text-slate-300 mb-2">Reply Text</label>
    <textarea name="reply_text" rows="6"
              class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">{{ old('reply_text', $rule->reply_text ?? '') }}</textarea>
</div>

<div class="mt-8">
    <h3 class="text-lg font-semibold text-white mb-4">Allowed Group Keys</h3>
    <p class="text-sm text-slate-400 mb-4">Kalau kosong, rule berlaku untuk semua grup.</p>

    <div id="rule-group-wrapper" class="space-y-4">
        @foreach($ruleGroups as $groupKey)
            <div class="group-item">
                <input type="text" name="group_keys[]" value="{{ $groupKey }}" placeholder="Group Key"
                       class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
            </div>
        @endforeach
    </div>

    <button type="button" onclick="addRuleGroupField()"
            class="mt-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 font-semibold">
        + Tambah Group Key
    </button>
</div>

<script>
function addRuleGroupField() {
    const wrapper = document.getElementById('rule-group-wrapper');
    const div = document.createElement('div');
    div.className = 'group-item mt-4';
    div.innerHTML = `
        <input type="text" name="group_keys[]" placeholder="Group Key"
               class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-indigo-500 focus:ring-0">
    `;
    wrapper.appendChild(div);
}
</script>