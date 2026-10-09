<?php
#App\GP247\Plugins\McpServer\AppConfig.php

namespace App\GP247\Plugins\McpServer;

use App\GP247\Plugins\McpServer\Services\TokenService;
use App\GP247\Plugins\McpServer\Support\AdminPrefix;
use App\GP247\Plugins\McpServer\Support\Routes;
use App\GP247\Plugins\McpServer\Support\Settings;
use GP247\Core\ExtensionConfigDefault;
use GP247\Core\Models\AdminConfig;
use GP247\Core\Models\AdminHome;
use GP247\Core\Models\AdminMenu;
use GP247\Core\Models\AdminPermission;

/**
 * Lifecycle of the McpServer plugin. install() and update() converge to the same state
 * (settings rows, menu, permissions) and are safe to repeat; the plugin owns no table.
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-endpoint
 * @aidlc-story US-plugin-mcp-server-access-token
 */
class AppConfig extends ExtensionConfigDefault
{
    private const MENU_PARENT_KEY = 'ADMIN_SYSTEM';

    /**
     * Read the manifest.
     */
    public function __construct()
    {
        $config = json_decode((string) file_get_contents(__DIR__ . '/gp247.json'), true);
        $this->configGroup = $config['configGroup'];
        $this->configKey = $config['configKey'];
        $this->configCode = $config['configCode'] ?? $this->configKey;
        $this->requireCore = $config['requireCore'] ?? [];
        $this->requireComposerPackages = $config['requireComposerPackages'] ?? [];
        $this->requireGp247Extensions = $config['requireGp247Extensions'] ?? [];
        $this->appPath = $this->configGroup . '/' . $this->configKey;
        $this->title = trans($this->appPath . '::lang.title');
        $this->image = $this->appPath . '/' . $config['image'];
        $this->version = $config['version'];
        $this->auth = $config['auth'];
        $this->link = $config['link'];
    }

    /**
     * Permissions of the plugin: "use" = token screen (and MCP itself), "configure" = settings screen.
     *
     * @return array<int, array{slug: string, name: string, http_uri: string}>
     */
    public static function permissionDefinitions(): array
    {
        $base = AdminPrefix::path(Routes::ADMIN_URI);

        return [
            ['slug' => 'mcp-server.use', 'name' => 'MCP — use (own tokens)', 'http_uri' => 'ANY::' . $base . '/tokens'],
            ['slug' => 'mcp-server.configure', 'name' => 'MCP — configure', 'http_uri' => 'ANY::' . $base . '/settings'],
        ];
    }

    /**
     * Install the plugin.
     */
    public function install()
    {
        if (AdminConfig::where('key', $this->configKey)->where('group', $this->configGroup)->exists()) {
            return ['error' => 1, 'msg' => gp247_language_render('admin.extension.plugin_exist')];
        }

        try {
            AdminConfig::insert([[
                'group' => $this->configGroup,
                'code' => $this->configCode,
                'key' => $this->configKey,
                'sort' => 0,
                'store_id' => GP247_STORE_ID_GLOBAL,
                'value' => self::ON,
                'detail' => $this->appPath . '::lang.title',
            ]]);
            $this->converge();

            return ['error' => 0, 'msg' => gp247_language_render('admin.extension.install_success')];
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Apply an update of the plugin files (idempotent).
     */
    public function update(?string $fromVersion = null)
    {
        try {
            $this->converge();
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }

        return ['error' => 0, 'msg' => ''];
    }

    /**
     * Uninstall: remove settings, menu, permissions and revoke every MCP token
     * (removing the plugin must cut all agent access). Other API tokens are untouched.
     */
    public function uninstall()
    {
        try {
            (new AdminConfig)
                ->where('key', $this->configKey)
                ->orWhere('code', Settings::CODE)
                ->delete();
            AdminHome::where('extension', $this->appPath)->delete();
            AdminMenu::where('key', $this->configKey)->delete();
            AdminPermission::whereIn('slug', array_column(self::permissionDefinitions(), 'slug'))->delete();
            app(TokenService::class)->revokeAll();
            Settings::flush();

            return ['error' => 0, 'msg' => gp247_language_render('admin.extension.uninstall_success')];
        } catch (\Throwable $e) {
            return ['error' => 1, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Enable the plugin.
     */
    public function enable()
    {
        (new AdminConfig)->where('group', $this->configGroup)->where('key', $this->configKey)->update(['value' => self::ON]);

        return ['error' => 0, 'msg' => gp247_language_render('admin.extension.enable_success')];
    }

    /**
     * Disable the plugin (routes disappear; tokens are kept for a later re-enable).
     */
    public function disable()
    {
        (new AdminConfig)->where('group', $this->configGroup)->where('key', $this->configKey)->update(['value' => self::OFF]);

        return ['error' => 0, 'msg' => ''];
    }

    /** Settings are site-wide; nothing to provision per store. */
    public function setupStore($storeId = null)
    {
    }

    /** Settings are site-wide; nothing to remove per store. */
    public function removeStore($storeId = null)
    {
    }

    /**
     * Open the token screen from the extension list.
     */
    public function clickApp()
    {
        return redirect(gp247_route_admin('admin_mcp_server.tokens'));
    }

    /**
     * Information shown in the extension list.
     */
    public function getInfo()
    {
        return [
            'title' => $this->title,
            'key' => $this->configKey,
            'code' => $this->configCode,
            'image' => $this->image,
            'permission' => self::ALLOW,
            'version' => $this->version,
            'auth' => $this->auth,
            'link' => $this->link,
            'value' => 0,
            'appPath' => $this->appPath,
        ];
    }

    /**
     * Bring settings rows, menu and permissions to the expected state.
     */
    protected function converge(): void
    {
        Settings::ensureRows();
        $this->installMenu();
        $this->installPermissions();
        Settings::flush();
    }

    private function installMenu(): void
    {
        if (AdminMenu::where('key', $this->configKey)->exists()) {
            return;
        }
        $parent = AdminMenu::where('key', self::MENU_PARENT_KEY)->first();
        if (!$parent) {
            return;
        }

        AdminMenu::insert([
            'sort' => 91,
            'parent_id' => $parent->id,
            'title' => $this->appPath . '::lang.title',
            'icon' => 'fas fa-robot',
            'uri' => 'route_admin::admin_mcp_server.tokens',
            'key' => $this->configKey,
        ]);
    }

    private function installPermissions(): void
    {
        foreach (self::permissionDefinitions() as $definition) {
            $permission = AdminPermission::firstOrCreate(
                ['slug' => $definition['slug']],
                ['name' => $definition['name'], 'http_uri' => $definition['http_uri']]
            );
            if ($permission->http_uri !== $definition['http_uri']) {
                $permission->update(['http_uri' => $definition['http_uri']]);
            }
        }
    }
}
