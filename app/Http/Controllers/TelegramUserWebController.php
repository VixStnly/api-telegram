<?php

namespace App\Http\Controllers;

use App\Models\TelegramClientAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TelegramUserWebController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $usersQuery = TelegramClientAccount::query()
            ->select([
                'bot_chat_id',
                DB::raw('max(bot_user_id) as bot_user_id'),
                DB::raw('max(bot_username) as bot_username'),
                DB::raw('max(bot_first_name) as bot_first_name'),
                DB::raw('count(*) as userbot_count'),
                DB::raw("sum(case when auth_status = 'authorized' then 1 else 0 end) as authorized_count"),
                DB::raw('max(last_seen_at) as last_seen_at'),
                DB::raw('max(created_at) as first_seen_at'),
            ])
            ->whereNotNull('bot_chat_id')
            ->groupBy('bot_chat_id')
            ->orderByDesc(DB::raw('max(last_seen_at)'));

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('bot_chat_id', 'like', "%{$search}%")
                    ->orWhere('bot_user_id', 'like', "%{$search}%")
                    ->orWhere('bot_username', 'like', "%{$search}%")
                    ->orWhere('bot_first_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $users = $usersQuery->paginate(20)->withQueryString();

        $totalUsers = TelegramClientAccount::whereNotNull('bot_chat_id')
            ->distinct('bot_chat_id')
            ->count('bot_chat_id');
        $totalUserbots = TelegramClientAccount::whereNotNull('phone_number')->count();
        $authorizedUserbots = TelegramClientAccount::where('auth_status', 'authorized')->count();

        return view('telegram-users.index', compact(
            'users',
            'search',
            'totalUsers',
            'totalUserbots',
            'authorizedUserbots'
        ));
    }

    public function show(string $botChatId)
    {
        $accounts = TelegramClientAccount::query()
            ->where('bot_chat_id', $botChatId)
            ->withCount([
                'groups',
                'groups as active_groups_count' => fn ($query) => $query->where('status', 'active'),
                'shareMessages',
            ])
            ->latest()
            ->get();

        abort_if($accounts->isEmpty(), 404);

        $owner = $accounts->first();
        $totalShares = $accounts->sum('share_messages_count');
        $totalGroups = $accounts->sum('groups_count');
        $activeGroups = $accounts->sum('active_groups_count');

        return view('telegram-users.show', compact(
            'botChatId',
            'owner',
            'accounts',
            'totalShares',
            'totalGroups',
            'activeGroups'
        ));
    }
}
