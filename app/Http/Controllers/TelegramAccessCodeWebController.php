<?php

namespace App\Http\Controllers;

use App\Models\TelegramAccessCode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TelegramAccessCodeWebController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $codes = TelegramAccessCode::query()
            ->when($search, function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('telegram-access-codes.index', compact('codes', 'search'));
    }

    public function create()
    {
        return view('telegram-access-codes.create', [
            'accessCode' => new TelegramAccessCode([
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['code'] = $this->normalizeCode($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        TelegramAccessCode::create($data);

        return redirect()
            ->route('telegram-access-codes.index')
            ->with('success', 'Kode akses berhasil dibuat.');
    }

    public function edit(TelegramAccessCode $telegramAccessCode)
    {
        return view('telegram-access-codes.edit', [
            'accessCode' => $telegramAccessCode,
        ]);
    }

    public function update(Request $request, TelegramAccessCode $telegramAccessCode)
    {
        $data = $this->validatedData($request, $telegramAccessCode);
        $data['code'] = $this->normalizeCode($data['code']);
        $data['is_active'] = $request->boolean('is_active');

        $telegramAccessCode->update($data);

        return redirect()
            ->route('telegram-access-codes.index')
            ->with('success', 'Kode akses berhasil diperbarui.');
    }

    public function destroy(TelegramAccessCode $telegramAccessCode)
    {
        $telegramAccessCode->delete();

        return redirect()
            ->route('telegram-access-codes.index')
            ->with('success', 'Kode akses berhasil dihapus.');
    }

    protected function validatedData(Request $request, ?TelegramAccessCode $accessCode = null): array
    {
        $request->merge([
            'code' => $this->normalizeCode((string) $request->input('code', '')),
        ]);

        $ignoreId = $accessCode?->id;

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('telegram_access_codes', 'code')->ignore($ignoreId),
            ],
            'label' => ['nullable', 'string', 'max:255'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    protected function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
