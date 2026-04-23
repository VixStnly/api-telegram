<?php

namespace App\Http\Controllers;

use App\Models\AutoReplyRule;
use App\Models\AutoReplyRuleGroup;
use App\Models\ManagedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AutoReplyRuleWebController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $rules = AutoReplyRule::with(['groups', 'device'])
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('pattern', 'like', "%{$search}%")
                    ->orWhere('reply_text', 'like', "%{$search}%");
            })
            ->orderBy('priority', 'asc')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('auto-reply-rules.index', compact('rules', 'search'));
    }

    public function create()
    {
        $devices = ManagedDevice::where('is_active', true)->orderBy('device_name')->get();

        return view('auto-reply-rules.create', compact('devices'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'managed_device_id' => 'nullable|integer|exists:managed_devices,id',
            'name' => 'required|string|max:255',
            'match_type' => 'required|in:exact,contains,regex',
            'pattern' => 'required|string',
            'case_sensitive' => 'nullable|boolean',
            'reply_text' => 'required|string',
            'priority' => 'nullable|integer|min:1',
            'cooldown_seconds' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rule = AutoReplyRule::create([
            'managed_device_id' => $request->managed_device_id,
            'name' => $request->name,
            'match_type' => $request->match_type,
            'pattern' => $request->pattern,
            'case_sensitive' => $request->boolean('case_sensitive', false),
            'reply_text' => $request->reply_text,
            'priority' => $request->priority ?? 100,
            'cooldown_seconds' => $request->cooldown_seconds ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'meta' => null,
        ]);

        foreach (($request->group_keys ?? []) as $groupKey) {
            if (empty($groupKey)) {
                continue;
            }

            AutoReplyRuleGroup::create([
                'auto_reply_rule_id' => $rule->id,
                'group_key' => $groupKey,
            ]);
        }

        return redirect()->route('auto-reply-rules.index')->with('success', 'Rule berhasil ditambahkan.');
    }

    public function show($id)
    {
        $rule = AutoReplyRule::with(['groups', 'device', 'logs'])->findOrFail($id);

        return view('auto-reply-rules.show', compact('rule'));
    }

    public function edit($id)
    {
        $rule = AutoReplyRule::with('groups')->findOrFail($id);
        $devices = ManagedDevice::where('is_active', true)->orderBy('device_name')->get();

        return view('auto-reply-rules.edit', compact('rule', 'devices'));
    }

    public function update(Request $request, $id)
    {
        $rule = AutoReplyRule::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'managed_device_id' => 'nullable|integer|exists:managed_devices,id',
            'name' => 'required|string|max:255',
            'match_type' => 'required|in:exact,contains,regex',
            'pattern' => 'required|string',
            'case_sensitive' => 'nullable|boolean',
            'reply_text' => 'required|string',
            'priority' => 'nullable|integer|min:1',
            'cooldown_seconds' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $rule->update([
            'managed_device_id' => $request->managed_device_id,
            'name' => $request->name,
            'match_type' => $request->match_type,
            'pattern' => $request->pattern,
            'case_sensitive' => $request->boolean('case_sensitive', false),
            'reply_text' => $request->reply_text,
            'priority' => $request->priority ?? 100,
            'cooldown_seconds' => $request->cooldown_seconds ?? 0,
            'is_active' => $request->boolean('is_active', false),
            'meta' => null,
        ]);

        $rule->groups()->delete();

        foreach (($request->group_keys ?? []) as $groupKey) {
            if (empty($groupKey)) {
                continue;
            }

            AutoReplyRuleGroup::create([
                'auto_reply_rule_id' => $rule->id,
                'group_key' => $groupKey,
            ]);
        }

        return redirect()->route('auto-reply-rules.index')->with('success', 'Rule berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $rule = AutoReplyRule::findOrFail($id);
        $rule->delete();

        return redirect()->route('auto-reply-rules.index')->with('success', 'Rule berhasil dihapus.');
    }
}