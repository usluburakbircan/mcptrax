<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;

/**
 * Herkese açık status sayfası verisi. Kimlik doğrulaması yok; yalnızca
 * `is_public` işaretli monitörler slug üzerinden okunabilir. Hata ayrıntısı
 * (error_message) BİLEREK dışarı verilmiyor — iç altyapı bilgisi sızdırır.
 */
class StatusPageController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $monitor = Monitor::where('slug', $slug)->where('is_public', true)->first();

        if ($monitor === null) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $rollups = $monitor->rollups()
            ->where('hour_start', '>=', now()->subDays(90)->startOfHour())
            ->orderBy('hour_start')
            ->get(['hour_start', 'checks_count', 'failures_count', 'p50_ms', 'p95_ms']);

        $totalChecks = $rollups->sum('checks_count');
        $totalFailures = $rollups->sum('failures_count');

        // Günlük özet: status sayfasındaki 90 kutucuk. Gün bazında en kötü
        // durum gösterilir (o gün hiç hata yoksa yeşil).
        $days = $rollups
            ->groupBy(fn ($rollup) => $rollup->hour_start->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'checks' => $group->sum('checks_count'),
                'failures' => $group->sum('failures_count'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $monitor->name,
                'status' => $monitor->status,
                'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
                'uptime_90d' => $totalChecks > 0
                    ? round(100 * (1 - $totalFailures / $totalChecks), 3)
                    : null,
                'days' => $days,
                'latency' => [
                    'p50_ms' => (int) $rollups->whereNotNull('p50_ms')->avg('p50_ms') ?: null,
                    'p95_ms' => (int) $rollups->whereNotNull('p95_ms')->avg('p95_ms') ?: null,
                ],
            ],
        ]);
    }
}
