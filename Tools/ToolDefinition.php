<?php
#App\GP247\Plugins\McpServer\Tools\ToolDefinition.php

namespace App\GP247\Plugins\McpServer\Tools;

use App\GP247\Plugins\McpServer\Support\AdminPrefix;

/**
 * Static description of one MCP tool: its access level and the admin screen whose
 * permission it borrows (L3 of the authorization model).
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-rbac-tools
 * @aidlc-adr ADR-plugin-mcp-server-token-auth
 */
final class ToolDefinition
{
    public const READ = 'read';
    public const WRITE = 'write';

    /**
     * @param string      $name           Tool name exposed to MCP clients.
     * @param string      $level          self::READ or self::WRITE.
     * @param string|null $screen         Admin path relative to the admin prefix (may contain "{id}"); null = no screen check.
     * @param bool        $defaultEnabled Whether the site enables the tool right after install.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $level,
        public readonly ?string $screen,
        public readonly bool $defaultEnabled,
    ) {
    }

    /**
     * True for tools that change shop data.
     */
    public function isWrite(): bool
    {
        return $this->level === self::WRITE;
    }

    /**
     * Full admin path used for the RBAC check, or null when the tool has no screen.
     *
     * @param string|null $recordId Record id substituted for "{id}" (a placeholder is used when null).
     */
    public function screenUri(?string $recordId = null): ?string
    {
        if ($this->screen === null) {
            return null;
        }

        return AdminPrefix::path(str_replace('{id}', $recordId ?? '0', $this->screen));
    }
}
