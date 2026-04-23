<?php

namespace App\Http\Controllers;

use App\Models\AutoReplyLog;
use App\Models\AutoReplyRule;
use App\Models\ManagedDevice;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $totalDevices = ManagedDevice::count();
        $activeDevices = ManagedDevice::where('is_active', true)->count();
        $onlineDevices = ManagedDevice::where('status', 'online')->count();

        $totalRules = AutoReplyRule::count();
        $activeRules = AutoReplyRule::where('is_active', true)->count();

        $totalLogs = AutoReplyLog::count();
        $matchedLogs = AutoReplyLog::where('is_matched', true)->count();
        $repliedLogs = AutoReplyLog::where('is_replied', true)->count();

        $latestDevices = ManagedDevice::latest()->take(5)->get();
        $latestRules = AutoReplyRule::with('device')->orderBy('priority')->latest()->take(5)->get();
        $latestLogs = AutoReplyLog::with(['device', 'rule'])->latest()->take(10)->get();

        return view('dashboard', compact(
            'totalDevices',
            'activeDevices',
            'onlineDevices',
            'totalRules',
            'activeRules',
            'totalLogs',
            'matchedLogs',
            'repliedLogs',
            'latestDevices',
            'latestRules',
            'latestLogs'
        ));
    }
}