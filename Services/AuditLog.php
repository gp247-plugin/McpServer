<?php
#App\GP247\Plugins\McpServer\Services\AuditLog.php

namespace App\GP247\Plugins\McpServer\Services;

use App\GP247\Plugins\McpServer\Support\AdminPrefix;
use App\GP247\Plugins\McpServer\Support\SafeReport;
use GP247\Core\Models\AdminLog;
use GP247\Core\Models\AdminUser;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Writes admin_log rows for MCP tool calls (method "MCP") and token management.
 * Never stores token values, confirmation codes or raw contact details.
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-audit-log
 * @aidlc-story US-plugin-mcp-server-access-token
 */
class AuditLog
{
    public const OK = 'ok';
    public const DENIED = 'denied';
    public const ERROR = 'error';

    private const MAX_INPUT_BYTES = 60000;

    /**
     * Record one tools/call.
     *
     * @param array<string, mixed> $arguments Raw tool arguments (scrubbed here).
     */
    public function toolCall(?AdminUser $user, ?PersonalAccessToken $token, string $tool, array $arguments, string $outcome, ?string $error = null): void
    {
        $this->write(
            $user,
            'mcp/' . $tool,
            'MCP',
            [
                'token' => ['id' => $token?->getKey(), 'name' => $token?->name],
                'arguments' => $this->scrubArguments($arguments),
                'outcome' => $outcome,
                'error' => $error === null ? null : PiiMasker::scrub($error),
            ]
        );
    }

    /**
     * Record a token-screen action (create / revoke).
     *
     * @param array<string, mixed> $data Non-secret details.
     */
    public function tokenAction(?AdminUser $user, string $action, array $data): void
    {
        $this->write($user, AdminPrefix::path('mcp-server/tokens'), 'LIVEWIRE', ['action' => $action] + $data);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function scrubArguments(array $arguments): array
    {
        $clean = [];
        foreach ($arguments as $key => $value) {
            if ($key === 'confirm_code') {
                $clean[$key] = '[redacted]';
            } elseif (is_string($value)) {
                $clean[$key] = PiiMasker::scrub(mb_substr($value, 0, 500));
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = $value;
            } else {
                $clean[$key] = '[' . gettype($value) . ']';
            }
        }

        return $clean;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function write(?AdminUser $user, string $path, string $method, array $input): void
    {
        try {
            $json = (string) json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $request = request();
            AdminLog::query()->create([
                'user_id' => (string) ($user?->getKey() ?? ''),
                'path' => mb_substr($path, 0, 255),
                'method' => $method,
                'ip' => (string) $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'input' => strlen($json) > self::MAX_INPUT_BYTES ? mb_strcut($json, 0, self::MAX_INPUT_BYTES) : $json,
            ]);
        } catch (\Throwable $e) {
            // WHY: a failing log must not break the tool call; the failure is still reported (never silent).
            SafeReport::throwable($e);
        }
    }
}
