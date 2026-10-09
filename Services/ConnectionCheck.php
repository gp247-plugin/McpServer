<?php
#App\GP247\Plugins\McpServer\Services\ConnectionCheck.php

namespace App\GP247\Plugins\McpServer\Services;

use App\GP247\Plugins\McpServer\Support\Routes;
use App\GP247\Plugins\McpServer\Support\SafeReport;
use Illuminate\Support\Facades\Http;

/**
 * Calls the site's own probe endpoint to detect setups that break MCP clients
 * (web server dropping the Authorization header, no HTTPS).
 *
 * @aidlc-unit plugin-mcp-server
 * @aidlc-story US-plugin-mcp-server-connect-guide
 */
class ConnectionCheck
{
    /**
     * Run the check.
     *
     * @return array{status: string, https: bool} status: ok | header_missing | unreachable
     */
    public function run(): array
    {
        $url = Routes::probeUrl();
        try {
            $response = Http::timeout(5)->withoutRedirecting()->acceptJson()->withToken('gp247-mcp-probe')->get($url);
        } catch (\Throwable $e) {
            SafeReport::throwable($e);

            return ['status' => 'unreachable', 'https' => str_starts_with($url, 'https://')];
        }

        if (!$response->ok() || $response->json('ok') !== true) {
            return ['status' => 'unreachable', 'https' => str_starts_with($url, 'https://')];
        }

        return [
            'status' => $response->json('authorization_header') === true ? 'ok' : 'header_missing',
            'https' => (bool) $response->json('https'),
        ];
    }
}
