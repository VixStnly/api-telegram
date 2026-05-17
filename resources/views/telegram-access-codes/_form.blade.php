<div class="grid grid-cols-1 gap-6 md:grid-cols-2">
    <div>
        <label class="mb-2 block text-sm font-medium text-zinc-300">Kode</label>
        <input
            type="text"
            name="code"
            value="{{ old('code', $accessCode->code) }}"
            placeholder="contoh: VIX-2026"
            class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
        >
        <p class="mt-2 text-xs text-zinc-500">Kode akan disimpan huruf besar otomatis.</p>
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-zinc-300">Label</label>
        <input
            type="text"
            name="label"
            value="{{ old('label', $accessCode->label) }}"
            placeholder="Nama campaign / customer"
            class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
        >
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-zinc-300">Kuota Pemakaian</label>
        <input
            type="number"
            name="max_uses"
            min="1"
            value="{{ old('max_uses', $accessCode->max_uses) }}"
            placeholder="Contoh: 1 atau 3"
            class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
        >
        <p class="mt-2 text-xs text-zinc-500">Kosongkan kalau kode boleh dipakai tanpa batas.</p>
    </div>

    <div>
        <label class="mb-2 block text-sm font-medium text-zinc-300">Expired At</label>
        <input
            type="datetime-local"
            name="expires_at"
            value="{{ old('expires_at', optional($accessCode->expires_at)->format('Y-m-d\TH:i')) }}"
            class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
        >
    </div>

    <div class="md:col-span-2">
        <label class="mb-2 block text-sm font-medium text-zinc-300">Catatan</label>
        <textarea
            name="notes"
            rows="4"
            placeholder="Opsional"
            class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-white placeholder-zinc-500 outline-none transition focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20"
        >{{ old('notes', $accessCode->notes) }}</textarea>
    </div>

    <label class="flex items-center gap-3 md:col-span-2">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            {{ old('is_active', $accessCode->is_active ?? true) ? 'checked' : '' }}
            class="rounded border-white/20 bg-black/30 text-emerald-500 focus:ring-emerald-500"
        >
        <span class="text-sm text-zinc-300">Kode aktif</span>
    </label>
</div>
