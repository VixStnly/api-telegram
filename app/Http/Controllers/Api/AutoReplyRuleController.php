<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutoReplyRule;
use App\Models\AutoReplyRuleGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AutoReplyRuleController extends Controller
{
    public function index()
    {
        $rules = AutoReplyRule::with(['groups', 'device'])
            ->orderBy('priority', 'asc')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Rules fetched successfully',
            'data' => $rules,
        ]);
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
            'meta' => 'nullable|array',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $rule = AutoReplyRule::create([
            'managed_device_id' => $data['managed_device_id'] ?? null,
            'name' => $data['name'],
            'match_type' => $data['match_type'],
            'pattern' => $data['pattern'],
            'case_sensitive' => $data['case_sensitive'] ?? false,
            'reply_text' => $data['reply_text'],
            'priority' => $data['priority'] ?? 100,
            'cooldown_seconds' => $data['cooldown_seconds'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'meta' => $data['meta'] ?? null,
        ]);

        foreach (($data['group_keys'] ?? []) as $groupKey) {
            if (empty($groupKey)) {
                continue;
            }

            AutoReplyRuleGroup::create([
                'auto_reply_rule_id' => $rule->id,
                'group_key' => $groupKey,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Rule created successfully',
            'data' => $rule->load(['groups', 'device']),
        ]);
    }

    public function show($id)
    {
        $rule = AutoReplyRule::with(['groups', 'device'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Rule fetched successfully',
            'data' => $rule,
        ]);
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
            'meta' => 'nullable|array',
            'group_keys' => 'nullable|array',
            'group_keys.*' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $rule->update([
            'managed_device_id' => $data['managed_device_id'] ?? null,
            'name' => $data['name'],
            'match_type' => $data['match_type'],
            'pattern' => $data['pattern'],
            'case_sensitive' => $data['case_sensitive'] ?? false,
            'reply_text' => $data['reply_text'],
            'priority' => $data['priority'] ?? 100,
            'cooldown_seconds' => $data['cooldown_seconds'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'meta' => $data['meta'] ?? null,
        ]);

        $rule->groups()->delete();

        foreach (($data['group_keys'] ?? []) as $groupKey) {
            if (empty($groupKey)) {
                continue;
            }

            AutoReplyRuleGroup::create([
                'auto_reply_rule_id' => $rule->id,
                'group_key' => $groupKey,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Rule updated successfully',
            'data' => $rule->load(['groups', 'device']),
        ]);
    }

    public function destroy($id)
    {
        $rule = AutoReplyRule::findOrFail($id);
        $rule->delete();

        return response()->json([
            'status' => true,
            'message' => 'Rule deleted successfully',
        ]);
    }
}