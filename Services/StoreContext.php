<?php
#App\GP247\Plugins\McpServer\Services\StoreContext.php

namespace App\GP247\Plugins\McpServer\Services;

use App\GP247\Plugins\McpServer\Support\SafeReport;
use GP247\Core\Models\AdminUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Store scope (L4) of an MCP request — ADR-plugin-mcp-server-store-resolution.
 *
 * MCP requests carry no session, so the admin store resolver (session based) cannot be used:
 * a partitioned site must register gp247-config.mcp.store_resolver, otherwise only
 * administrators (ROOT) may connect. Any doubt resolves to "denied".
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-rbac-tools
 * @aidlc-adr ADR-plugin-mcp-server-store-resolution
 */
class StoreContext
{
    /**
     * Store id the user works on, or null when MCP must be refused.
     */
    public function resolve(AdminUser $user): ?string
    {
        $mcpResolver = config('gp247-config.mcp.store_resolver');
        if ($mcpResolver !== null && $mcpResolver !== '') {
            if (!is_callable($mcpResolver)) {
                return null;
            }
            try {
                $storeId = $mcpResolver($user);
            } catch (\Throwable $e) {
                SafeReport::throwable($e);

                return null;
            }

            return ($storeId === null || $storeId === '') ? null : (string) $storeId;
        }

        $adminResolver = config('gp247-config.admin.store_resolver');
        if (!empty($adminResolver) && is_callable($adminResolver)) {
            return $user->isAdministrator() ? $this->root() : null;
        }

        return $this->root();
    }

    /**
     * Limit a query to the store scope (ROOT on a partitioned site sees every store, like the admin lists).
     */
    public function constrain(Builder $query, string $storeId, string $column = 'store_id'): Builder
    {
        if ($this->seesAllStores($storeId)) {
            return $query;
        }

        return $query->where($query->getModel()->qualifyColumn($column), $storeId);
    }

    /**
     * Whether a record of the given store is inside the scope.
     */
    public function contains(string $storeId, $recordStoreId): bool
    {
        return $this->seesAllStores($storeId) || (string) $recordStoreId === $storeId;
    }

    private function seesAllStores(string $storeId): bool
    {
        if ($storeId !== $this->root()) {
            return false;
        }

        return (function_exists('gp247_store_check_multi_store_installed') && gp247_store_check_multi_store_installed())
            || (function_exists('gp247_store_check_multi_partner_installed') && gp247_store_check_multi_partner_installed());
    }

    private function root(): string
    {
        return (string) (defined('GP247_STORE_ID_ROOT') ? GP247_STORE_ID_ROOT : '1');
    }
}
