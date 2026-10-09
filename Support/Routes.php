<?php
#App\GP247\Plugins\McpServer\Support\Routes.php

namespace App\GP247\Plugins\McpServer\Support;

use App\GP247\Plugins\McpServer\Http\Controllers\ProbeController;
use App\GP247\Plugins\McpServer\Http\Middleware\McpEdgeGuard;
use App\GP247\Plugins\McpServer\Http\Middleware\McpEndpointGate;
use App\GP247\Plugins\McpServer\Livewire\McpSettings;
use App\GP247\Plugins\McpServer\Livewire\TokenManager;
use App\GP247\Plugins\McpServer\Mcp\GP247Server;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/**
 * Routes of the plugin: two admin screens and the MCP endpoint (bare route outside the
 * web/admin/api groups — ADR-plugin-mcp-server-endpoint-placement).
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-endpoint
 * @aidlc-adr ADR-plugin-mcp-server-endpoint-placement
 */
final class Routes
{
    public const ADMIN_URI = 'mcp-server';
    public const ENDPOINT = 'api/core/mcp';
    public const PROBE = 'api/core/mcp-probe';

    /**
     * Register every route of the plugin.
     */
    public static function register(): void
    {
        Route::group(
            ['prefix' => GP247_ADMIN_PREFIX . '/' . self::ADMIN_URI, 'middleware' => GP247_ADMIN_MIDDLEWARE],
            static function (): void {
                Route::get('tokens', TokenManager::class)->name('admin_mcp_server.tokens');
                Route::get('settings', McpSettings::class)->name('admin_mcp_server.settings');
            }
        );

        // Also registered by Provider.php: with `route:cache` this file is not executed, but the
        // limiters must still exist or `throttle:mcp` throws on every call.
        self::registerRateLimiters();

        $middleware = [McpEndpointGate::class, 'auth:admin-api', McpEdgeGuard::class, 'throttle:mcp'];
        if (array_key_exists('check_ip', app('router')->getMiddleware())) {
            // WHY: the CheckIP plugin protects the admin and API groups; this bare route must get it too.
            array_unshift($middleware, 'check_ip');
        }
        Mcp::web(self::ENDPOINT, GP247Server::class)->middleware($middleware)->name('mcp_server.endpoint');

        // WHY: laravel/mcp also adds GET/DELETE (405) on the same URI; gate them so a disabled
        // endpoint answers 404 there too and does not reveal the plugin.
        foreach (['GET', 'HEAD', 'DELETE'] as $method) {
            $route = Route::getRoutes()->getRoutesByMethod()[$method][self::ENDPOINT] ?? null;
            $route?->middleware(McpEndpointGate::class);
        }

        Route::get(self::PROBE, ProbeController::class)->middleware('throttle:30,1')->name('mcp_server.probe');
    }

    /**
     * Absolute URL of the MCP endpoint, shown to users (current domain, so each store domain shows its own).
     */
    public static function endpointUrl(): string
    {
        return url(self::ENDPOINT);
    }

    /**
     * Absolute URL of the diagnostic probe, built from APP_URL — never from the request Host header,
     * so the server-side check cannot be pointed at another host (SSRF).
     */
    public static function probeUrl(): string
    {
        return rtrim((string) config('app.url'), '/') . '/' . self::PROBE;
    }

    /**
     * Rate limiter "mcp" (after authentication): per token, with a wider per-IP bound. The pre-auth
     * per-IP budget lives in McpEndpointGate (middleware priority would move a throttle after auth).
     */
    public static function registerRateLimiters(): void
    {
        RateLimiter::for('mcp', static function (Request $request): array {
            $perMinute = Settings::int('throttle_per_minute', 1, 10000);
            $tokenId = $request->user()?->currentAccessToken()?->getKey();

            return [
                Limit::perMinute($perMinute)->by('mcp-token:' . ($tokenId ?? $request->ip())),
                // WHY a wider per-IP limit: several leaked tokens used from one host stay bounded.
                Limit::perMinute($perMinute * 5)->by('mcp-ip:' . $request->ip()),
            ];
        });
    }
}
