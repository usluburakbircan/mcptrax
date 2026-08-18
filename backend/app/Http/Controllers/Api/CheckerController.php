<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use App\Probes\McpProbe;
use App\Probes\UrlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kayıt gerektirmeyen "check my MCP server" aracı: tek seferlik, senkron
 * handshake + tools/list. Landing'in lead magnet'i olduğu için hız ve
 * anlaşılır rapor önceliklidir; sonuç hiçbir yere kaydedilmez.
 */
class CheckerController extends Controller
{
    public function check(Request $request, McpProbe $probe): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
        ]);

        if (! app()->environment('local', 'testing') && ! UrlGuard::isSafe($data['url'])) {
            return response()->json([
                'success' => false,
                'message' => 'This address cannot be checked. Only publicly reachable MCP servers are supported.',
            ], 422);
        }

        $monitor = new Monitor(['url' => $data['url']]);

        $result = $probe->run($monitor);

        return response()->json([
            'success' => true,
            'data' => [
                'report' => [
                    'ok' => $result->ok,
                    'failed_phase' => $result->failedPhase,
                    'error_class' => $result->errorClass ? class_basename($result->errorClass) : null,
                    'error_message' => $result->errorMessage,
                    'connect_ms' => $result->connectMs,
                    'tools_list_ms' => $result->toolsListMs,
                    'total_ms' => $result->totalMs(),
                    'server_name' => $result->serverName,
                    'server_version' => $result->serverVersion,
                    'protocol_version' => $result->protocolVersion,
                    'tools' => $result->toolDetails,
                ],
            ],
        ]);
    }
}
