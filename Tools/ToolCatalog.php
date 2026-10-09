<?php
#App\GP247\Plugins\McpServer\Tools\ToolCatalog.php

namespace App\GP247\Plugins\McpServer\Tools;

/**
 * Single registry of the tools the plugin can expose (Unit "Tool catalog v1").
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-rbac-tools
 */
final class ToolCatalog
{
    /** @var array<string, ToolDefinition>|null */
    private static ?array $tools = null;

    /**
     * All tool definitions keyed by name.
     *
     * @return array<string, ToolDefinition>
     */
    public static function all(): array
    {
        if (self::$tools !== null) {
            return self::$tools;
        }

        $read = ToolDefinition::READ;
        $definitions = [
            new ToolDefinition('store_info', $read, null, true),
            new ToolDefinition('search_products', $read, 'product', true),
            new ToolDefinition('get_product', $read, 'product', true),
            new ToolDefinition('list_categories', $read, 'category', true),
            new ToolDefinition('low_stock_products', $read, 'product', true),
            new ToolDefinition('search_orders', $read, 'order', true),
            new ToolDefinition('get_order', $read, 'order', true),
            new ToolDefinition('search_customers', $read, 'customer', true),
            new ToolDefinition('get_customer', $read, 'customer', true),
            new ToolDefinition('sales_summary', $read, 'report/product', true),
            new ToolDefinition('update_order_status', ToolDefinition::WRITE, 'order/detail/{id}', false),
        ];

        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->name] = $definition;
        }

        return self::$tools = $map;
    }

    /**
     * Definition by name, or null for an unknown tool.
     */
    public static function find(string $name): ?ToolDefinition
    {
        return self::all()[$name] ?? null;
    }

    /**
     * Definition by name; throws for an unknown tool (programming error).
     */
    public static function get(string $name): ToolDefinition
    {
        return self::find($name) ?? throw new \InvalidArgumentException('Unknown MCP tool: ' . $name);
    }

    /**
     * @return string[] Tool names.
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }
}
