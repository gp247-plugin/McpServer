<?php
/**
 * Provider of the McpServer plugin (required by the core for every installed plugin).
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-endpoint
 */

$config = file_get_contents(__DIR__ . '/gp247.json');
$config = json_decode($config, true);
$extensionPath = $config['configGroup'] . '/' . $config['configKey'];

$this->loadTranslationsFrom(__DIR__ . '/Lang', $extensionPath);

if (gp247_extension_check_active($config['configGroup'], $config['configKey'])) {
    $this->loadViewsFrom(__DIR__ . '/Views', $extensionPath);

    if (class_exists(\Livewire\Livewire::class)) {
        \Livewire\Livewire::addNamespace('McpServer', classNamespace: 'App\\GP247\\Plugins\\McpServer\\Livewire');
    }

    // WHY booted(): SandboxDemo merges its config after this provider (alphabetical load order).
    // Rate limiters live here too because Route.php is skipped when routes are cached.
    $this->app->booted(static function (): void {
        \App\GP247\Plugins\McpServer\Support\Integrations::register();
        \App\GP247\Plugins\McpServer\Support\Routes::registerRateLimiters();
    });
}
