<?php

namespace App\Http\Controllers;

use App\Models\AutoReplyLog;
use App\Models\ManagedDevice;
use Illuminate\Http\Request;

class AutoReplyLogWebController extends Controller
{
    public function index(Request $request)
    {
        $devices = ManagedDevice::orderBy('device_name')->get();

        $query = AutoReplyLog::with(['device', 'rule'])->latest();

        if ($request->filled('managed_device_id')) {
            $query->where('managed_device_id', $request->managed_device_id);
        }

        if ($request->filled('group_key')) {
            $query->where('group_key', 'like', '%' . $request->group_key . '%');
        }

        if ($request->filled('is_matched')) {
            $query->where('is_matched', $request->is_matched);
        }

        if ($request->filled('is_replied')) {
            $query->where('is_replied', $request->is_replied);
        }

        $logs = $query->paginate(20)->withQueryString();

        return view('auto-reply-logs.index', compact('logs', 'devices'));
    }

    public function show($id)
    {
        $log = AutoReplyLog::with(['device', 'rule'])->findOrFail($id);

        return view('auto-reply-logs.show', compact('log'));
    }
}