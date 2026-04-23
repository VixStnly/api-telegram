<?php

namespace App\Http\Controllers;

use App\Models\ManagedDevice;
use App\Services\AutoReplyEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AutoReplyTestWebController extends Controller
{
    public function index()
    {
        $devices = ManagedDevice::query()
            ->where('is_active', true)
            ->orderBy('device_name')
            ->get();

        return view('auto-reply-test.index', [
            'devices' => $devices,
            'result' => session('result'),
        ]);
    }

    public function process(Request $request, AutoReplyEngine $engine)
    {
        $validator = Validator::make($request->all(), [
            'managed_device_id' => 'nullable|integer|exists:managed_devices,id',
            'group_key' => 'nullable|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'sender_key' => 'nullable|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'message_text' => 'required|string',
        ], [
            'message_text.required' => 'Message text wajib diisi.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->back()
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        $managedDeviceId = !empty($data['managed_device_id']) ? (int) $data['managed_device_id'] : null;
        $groupKey = $data['group_key'] ?? null;
        $groupName = $data['group_name'] ?? null;
        $senderKey = $data['sender_key'] ?? null;
        $senderName = $data['sender_name'] ?? null;
        $messageText = $data['message_text'] ?? '';

        if ($managedDeviceId) {
            ManagedDevice::where('id', $managedDeviceId)->update([
                'last_seen_at' => now(),
                'status' => 'online',
            ]);
        }

        $result = $engine->process([
            'managed_device_id' => $managedDeviceId,
            'group_key' => $groupKey,
            'group_name' => $groupName,
            'sender_key' => $senderKey,
            'sender_name' => $senderName,
            'message_text' => $messageText,
            'meta' => [
                'source' => 'dashboard_test_page',
            ],
        ]);

        return redirect()
            ->route('auto-reply-test.index')
            ->withInput()
            ->with('result', [
                'matched' => $result['matched'] ?? false,
                'replied' => $result['replied'] ?? false,
                'skip_reason' => $result['skip_reason'] ?? null,
                'reply_text' => $result['reply_text'] ?? null,
                'rule_id' => $result['rule_id'] ?? null,
            ]);
    }
}