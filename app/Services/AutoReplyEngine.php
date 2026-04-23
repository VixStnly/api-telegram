<?php

namespace App\Services;

use App\Models\AutoReplyLog;
use App\Models\AutoReplyRule;
use Carbon\Carbon;

class AutoReplyEngine
{
    public function process(array $payload): array
    {
        $managedDeviceId = $payload['managed_device_id'] ?? null;
        $groupKey = $payload['group_key'] ?? null;
        $groupName = $payload['group_name'] ?? null;
        $senderKey = $payload['sender_key'] ?? null;
        $senderName = $payload['sender_name'] ?? null;
        $messageText = trim((string) ($payload['message_text'] ?? ''));
        $meta = $payload['meta'] ?? [];

        $log = AutoReplyLog::create([
            'managed_device_id' => $managedDeviceId,
            'group_key' => $groupKey,
            'group_name' => $groupName,
            'sender_key' => $senderKey,
            'sender_name' => $senderName,
            'message_text' => $messageText,
            'is_matched' => false,
            'is_replied' => false,
            'skip_reason' => null,
            'reply_text' => null,
            'meta' => $meta,
            'processed_at' => now(),
        ]);

        if ($messageText === '') {
            $log->update([
                'skip_reason' => 'EMPTY_MESSAGE',
            ]);

            return [
                'matched' => false,
                'replied' => false,
                'skip_reason' => 'EMPTY_MESSAGE',
                'reply_text' => null,
                'rule_id' => null,
            ];
        }

        $rules = AutoReplyRule::query()
            ->with('groups')
            ->where('is_active', true)
            ->where(function ($query) use ($managedDeviceId) {
                $query->whereNull('managed_device_id');

                if (!empty($managedDeviceId)) {
                    $query->orWhere('managed_device_id', $managedDeviceId);
                }
            })
            ->orderByRaw('CASE WHEN managed_device_id IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($rules as $rule) {
            if (!$this->isGroupAllowed($rule, $groupKey)) {
                continue;
            }

            if (!$this->isMatched($rule, $messageText)) {
                continue;
            }

            if ($this->isOnCooldown($rule, $managedDeviceId, $groupKey)) {
                $log->update([
                    'matched_rule_id' => $rule->id,
                    'is_matched' => true,
                    'is_replied' => false,
                    'skip_reason' => 'COOLDOWN_ACTIVE',
                    'reply_text' => null,
                ]);

                return [
                    'matched' => true,
                    'replied' => false,
                    'skip_reason' => 'COOLDOWN_ACTIVE',
                    'reply_text' => null,
                    'rule_id' => $rule->id,
                ];
            }

            $replyText = $this->renderReply($rule->reply_text, [
                'device_id' => $managedDeviceId,
                'group_key' => $groupKey,
                'group_name' => $groupName,
                'sender_key' => $senderKey,
                'sender_name' => $senderName,
                'message_text' => $messageText,
            ]);

            $log->update([
                'matched_rule_id' => $rule->id,
                'is_matched' => true,
                'is_replied' => true,
                'skip_reason' => null,
                'reply_text' => $replyText,
            ]);

            return [
                'matched' => true,
                'replied' => true,
                'skip_reason' => null,
                'reply_text' => $replyText,
                'rule_id' => $rule->id,
            ];
        }

        $log->update([
            'skip_reason' => 'NO_RULE_MATCHED',
        ]);

        return [
            'matched' => false,
            'replied' => false,
            'skip_reason' => 'NO_RULE_MATCHED',
            'reply_text' => null,
            'rule_id' => null,
        ];
    }

    protected function isGroupAllowed(AutoReplyRule $rule, ?string $groupKey): bool
    {
        $groups = $rule->groups;

        if ($groups->isEmpty()) {
            return true;
        }

        if (empty($groupKey)) {
            return false;
        }

        return $groups->contains(function ($item) use ($groupKey) {
            return $item->group_key === $groupKey;
        });
    }

    protected function isMatched(AutoReplyRule $rule, string $messageText): bool
    {
        $pattern = $rule->pattern;
        $matchType = $rule->match_type;
        $caseSensitive = $rule->case_sensitive;

        return match ($matchType) {
            'exact' => $this->matchExact($messageText, $pattern, $caseSensitive),
            'contains' => $this->matchContains($messageText, $pattern, $caseSensitive),
            'regex' => $this->matchRegex($messageText, $pattern, $caseSensitive),
            default => false,
        };
    }

    protected function matchExact(string $message, string $pattern, bool $caseSensitive): bool
    {
        return $caseSensitive
            ? $message === $pattern
            : mb_strtolower($message) === mb_strtolower($pattern);
    }

    protected function matchContains(string $message, string $pattern, bool $caseSensitive): bool
    {
        if ($caseSensitive) {
            return str_contains($message, $pattern);
        }

        return str_contains(
            mb_strtolower($message),
            mb_strtolower($pattern)
        );
    }

    protected function matchRegex(string $message, string $pattern, bool $caseSensitive): bool
    {
        $delimiter = '/';
        $modifiers = $caseSensitive ? 'u' : 'iu';
        $fullPattern = $delimiter . $pattern . $delimiter . $modifiers;

        try {
            return preg_match($fullPattern, $message) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function isOnCooldown(AutoReplyRule $rule, ?int $managedDeviceId, ?string $groupKey): bool
    {
        if (($rule->cooldown_seconds ?? 0) <= 0) {
            return false;
        }

        $threshold = Carbon::now()->subSeconds($rule->cooldown_seconds);

        $query = AutoReplyLog::query()
            ->where('matched_rule_id', $rule->id)
            ->where('is_replied', true)
            ->where('created_at', '>=', $threshold);

        if (!empty($managedDeviceId)) {
            $query->where('managed_device_id', $managedDeviceId);
        }

        if (!empty($groupKey)) {
            $query->where('group_key', $groupKey);
        }

        return $query->exists();
    }

    protected function renderReply(string $replyText, array $context = []): string
    {
        $replace = [
            '{device_id}' => (string) ($context['device_id'] ?? ''),
            '{group_key}' => (string) ($context['group_key'] ?? ''),
            '{group_name}' => (string) ($context['group_name'] ?? ''),
            '{sender_key}' => (string) ($context['sender_key'] ?? ''),
            '{sender_name}' => (string) ($context['sender_name'] ?? ''),
            '{message_text}' => (string) ($context['message_text'] ?? ''),
            '{date}' => now()->format('Y-m-d'),
            '{time}' => now()->format('H:i:s'),
        ];

        return strtr($replyText, $replace);
    }
}