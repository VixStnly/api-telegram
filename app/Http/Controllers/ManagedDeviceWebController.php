<?php

namespace App\Http\Controllers;

use App\Models\ManagedDevice;
use App\Models\ManagedDeviceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ManagedDeviceWebController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $devices = ManagedDevice::with('groups')
            ->when($search, function ($query) use ($search) {
                $query->where('device_name', 'like', "%{$search}%")
                    ->orWhere('device_code', 'like', "%{$search}%")
                    ->orWhere('account_label', 'like', "%{$search}%")
                    ->orWhere('account_identifier', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('managed-devices.index', compact('devices', 'search'));
    }

    public function create()
    {
        return view('managed-devices.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_name' => 'required|string|max:255',
            'account_label' => 'nullable|string|max:255',
            'account_identifier' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:100',
            'session_name' => 'nullable|string|max:255',
            'session_token' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
            'group_names' => 'nullable|array',
            'group_names.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $device = ManagedDevice::create([
            'device_name' => $request->device_name,
            'device_code' => $this->generateUniqueDeviceCode(),
            'account_label' => $request->account_label,
            'account_identifier' => $request->account_identifier,
            'platform' => $request->platform,
            'session_name' => $request->session_name,
            'session_token' => $request->session_token,
            'status' => $request->status ?? 'inactive',
            'last_seen_at' => null,
            'meta' => null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $groupKeys = $request->group_keys ?? [];
        $groupNames = $request->group_names ?? [];

        foreach ($groupKeys as $index => $groupKey) {
            if (empty($groupKey)) {
                continue;
            }

            ManagedDeviceGroup::create([
                'managed_device_id' => $device->id,
                'group_key' => $groupKey,
                'group_name' => $groupNames[$index] ?? null,
                'meta' => null,
            ]);
        }

        return redirect()->route('managed-devices.index')->with('success', 'Managed device berhasil ditambahkan.');
    }

    public function show($id)
    {
        $device = ManagedDevice::with(['groups', 'rules', 'logs'])->findOrFail($id);

        return view('managed-devices.show', compact('device'));
    }

    public function edit($id)
    {
        $device = ManagedDevice::with('groups')->findOrFail($id);

        return view('managed-devices.edit', compact('device'));
    }

    public function update(Request $request, $id)
    {
        $device = ManagedDevice::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'device_name' => 'required|string|max:255',
            'account_label' => 'nullable|string|max:255',
            'account_identifier' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:100',
            'session_name' => 'nullable|string|max:255',
            'session_token' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
            'group_names' => 'nullable|array',
            'group_names.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $device->update([
            'device_name' => $request->device_name,
            'account_label' => $request->account_label,
            'account_identifier' => $request->account_identifier,
            'platform' => $request->platform,
            'session_name' => $request->session_name,
            'session_token' => $request->session_token,
            'status' => $request->status ?? 'inactive',
            'is_active' => $request->boolean('is_active', false),
        ]);

        $device->groups()->delete();

        $groupKeys = $request->group_keys ?? [];
        $groupNames = $request->group_names ?? [];

        foreach ($groupKeys as $index => $groupKey) {
            if (empty($groupKey)) {
                continue;
            }

            ManagedDeviceGroup::create([
                'managed_device_id' => $device->id,
                'group_key' => $groupKey,
                'group_name' => $groupNames[$index] ?? null,
                'meta' => null,
            ]);
        }

        return redirect()->route('managed-devices.index')->with('success', 'Managed device berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $device = ManagedDevice::findOrFail($id);
        $device->delete();

        return redirect()->route('managed-devices.index')->with('success', 'Managed device berhasil dihapus.');
    }

    protected function generateUniqueDeviceCode(): string
    {
        do {
            $code = 'DEV-' . strtoupper(Str::random(10));
        } while (ManagedDevice::where('device_code', $code)->exists());

        return $code;
    }
}