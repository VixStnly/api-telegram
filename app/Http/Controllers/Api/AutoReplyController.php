<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManagedDevice;
use App\Services\AutoReplyEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AutoReplyController extends Controller
{
    public function process(Request $request, AutoReplyEngine $engine)
    {
        $validator = Validator::make($request->all(), [
            'managed_device_id' => 'nullable|integer|exists:managed_devices,id',
            'group_key' => 'nullable|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'sender_key' => 'nullable|string|max:255',
            'sender_name' => 'nullable|string|max:255',
            'message_text' => 'required|string',
            'meta' => 'nullable|array',
        ], [
            'message_text.required' => 'Message text wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        if (!empty($payload['managed_device_id'])) {
            ManagedDevice::where('id', $payload['managed_device_id'])->update([
                'last_seen_at' => now(),
                'status' => 'online',
            ]);
        }

        $result = $engine->process($payload);

        return response()->json([
            'status' => true,
            'message' => 'Message processed successfully',
            'data' => $result,
        ]);
    }
}