<?php
/**
 * Routes of the McpServer plugin, registered only while the plugin is active and the
 * laravel/mcp package is present (a missing package must not break the whole site).
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-endpoint
 */

$config = file_get_contents(__DIR__ . '/gp247.json');
$config = json_decode($config, true);

if (gp247_extension_check_active($config['configGroup'], $config['configKey'])
    && class_exists(\Laravel\Mcp\Server::class)) {
    \App\GP247\Plugins\McpServer\Support\Routes::register();
}
