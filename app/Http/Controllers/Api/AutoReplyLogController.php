<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoReplyLog;
use Illuminate\Http\Request;

class AutoReplyLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AutoReplyLog::with(['device', 'rule'])
            ->orderByDesc('id');

        if ($request->filled('managed_device_id')) {
            $query->where('managed_device_id', $request->managed_device_id);
        }

        if ($request->filled('group_key')) {
            $query->where('group_key', $request->group_key);
        }

        if ($request->filled('is_matched')) {
            $query->where('is_matched', $request->is_matched);
        }

        if ($request->filled('is_replied')) {
            $query->where('is_replied', $request->is_replied);
        }

        $logs = $query->paginate(20);

        return response()->json([
            'status' => true,
            'message' => 'Logs fetched successfully',
            'data' => $logs,
        ]);
    }

    public function show($id)
    {
        $log = AutoReplyLog::with(['device', 'rule'])->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Log fetched successfully',
            'data' => $log,
        ]);
    }
}