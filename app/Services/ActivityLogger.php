<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Writes append-only audit records to the shared activity_log table.
 *
 * Sensitive values (passwords, tokens, API keys, hashes) are redacted
 * recursively by property name before persistence. The setting key itself
 * stays readable: when a properties array carries a `key` entry whose value
 * is itself sensitive, the sibling `old_value`/`new_value` entries are
 * redacted wholesale instead.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, ?Model $subject = null, array $properties = [], ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $this->redact($properties),
            'user_id' => $userId ?? Auth::id(),
            'ip_address' => $this->requestIp(),
        ]);
    }

    /**
     * Audit a record without letting a storage failure break the primary
     * operation (source/API-key creation, settings saves, model updates).
     * Failures are logged loudly; the audit trail is best-effort.
     *
     * @param  array<string, mixed>  $properties
     */
    public function safeRecord(string $action, ?Model $subject = null, array $properties = [], ?int $userId = null): void
    {
        try {
            $this->record($action, $subject, $properties, $userId);
        } catch (\Throwable $e) {
            Log::warning('Activity audit write failed.', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function requestIp(): ?string
    {
        try {
            return request()?->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    private function redact(mixed $value, string $key = ''): mixed
    {
        if (is_array($value)) {
            $sensitiveSetting = is_string($value['key'] ?? null)
                && $this->isSensitiveKey($value['key']);

            $redacted = [];

            foreach ($value as $childKey => $childValue) {
                if ($sensitiveSetting && in_array((string) $childKey, ['old_value', 'new_value'], true)) {
                    $redacted[$childKey] = '[REDACTED]';

                    continue;
                }

                $redacted[$childKey] = $this->redact($childValue, (string) $childKey);
            }

            return $redacted;
        }

        if ($this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/\b(?:password|secret|token|auth_token|api_key|plain_key|key_hash)\b/i', $key);
    }
}
