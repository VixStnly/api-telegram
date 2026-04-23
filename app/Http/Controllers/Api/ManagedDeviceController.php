<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManagedDevice;
use App\Models\ManagedDeviceGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ManagedDeviceController extends Controller
{
    public function index()
    {
        $devices = ManagedDevice::with('groups')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Managed devices fetched successfully',
            'data' => $devices,
        ]);
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
            'meta' => 'nullable|array',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
            'group_names' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $device = ManagedDevice::create([
            'device_name' => $data['device_name'],
            'device_code' => $this->generateUniqueDeviceCode(),
            'account_label' => $data['account_label'] ?? null,
            'account_identifier' => $data['account_identifier'] ?? null,
            'platform' => $data['platform'] ?? null,
            'session_name' => $data['session_name'] ?? null,
            'session_token' => $data['session_token'] ?? null,
            'status' => $data['status'] ?? 'inactive',
            'last_seen_at' => null,
            'meta' => $data['meta'] ?? null,
            'is_active' => true,
        ]);

        $groupKeys = $data['group_keys'] ?? [];
        $groupNames = $data['group_names'] ?? [];

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

        return response()->json([
            'status' => true,
            'message' => 'Managed device created successfully',
            'data' => $device->load('groups'),
        ]);
    }

    public function show($id)
    {
        $device = ManagedDevice::with(['groups', 'rules', 'logs'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Managed device fetched successfully',
            'data' => $device,
        ]);
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
            'meta' => 'nullable|array',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
            'group_names' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $device->update([
            'device_name' => $data['device_name'],
            'account_label' => $data['account_label'] ?? null,
            'account_identifier' => $data['account_identifier'] ?? null,
            'platform' => $data['platform'] ?? null,
            'session_name' => $data['session_name'] ?? null,
            'session_token' => $data['session_token'] ?? null,
            'status' => $data['status'] ?? $device->status,
            'is_active' => $data['is_active'] ?? $device->is_active,
            'meta' => $data['meta'] ?? $device->meta,
        ]);

        if (array_key_exists('group_keys', $data)) {
            $device->groups()->delete();

            $groupKeys = $data['group_keys'] ?? [];
            $groupNames = $data['group_names'] ?? [];

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
        }

        return response()->json([
            'status' => true,
            'message' => 'Managed device updated successfully',
            'data' => $device->load('groups'),
        ]);
    }

    public function destroy($id)
    {
        $device = ManagedDevice::findOrFail($id);
        $device->delete();

        return response()->json([
            'status' => true,
            'message' => 'Managed device deleted successfully',
        ]);
    }

    protected function generateUniqueDeviceCode(): string
    {
        do {
            $code = 'DEV-' . strtoupper(Str::random(10));
        } while (ManagedDevice::where('device_code', $code)->exists());

        return $code;
    }
}