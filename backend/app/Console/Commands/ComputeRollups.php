<?php

namespace App\Console\Commands;

use App\Models\Check;
use App\Models\CheckRollup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ComputeRollups extends Command
{
    protected $signature = 'rollups:compute {--hour= : ISO saat başlangıcı; boşsa bir önceki saat}';

    protected $description = 'Aggregate raw checks into hourly per-monitor rollups (uptime + latency percentiles)';

    public function handle(): int
    {
        $hourStart = $this->option('hour')
            ? Carbon::parse($this->option('hour'))->startOfHour()
            : now()->subHour()->startOfHour();

        $hourEnd = $hourStart->copy()->addHour();

        $byMonitor = Check::query()
            ->whereBetween('started_at', [$hourStart, $hourEnd])
            ->get(['monitor_id', 'ok', 'latency_ms'])
            ->groupBy('monitor_id');

        foreach ($byMonitor as $monitorId => $checks) {
            $latencies = $checks->pluck('latency_ms')->filter()->sort()->values();

            CheckRollup::updateOrCreate(
                ['monitor_id' => $monitorId, 'hour_start' => $hourStart],
                [
                    'checks_count' => $checks->count(),
                    'failures_count' => $checks->where('ok', false)->count(),
                    'p50_ms' => $this->percentile($latencies, 0.50),
                    'p95_ms' => $this->percentile($latencies, 0.95),
                    'max_ms' => $latencies->last(),
                ],
            );
        }

        $this->info("Rolled up {$byMonitor->count()} monitor(s) for {$hourStart->toDateTimeString()}.");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $sorted
     */
    protected function percentile($sorted, float $p): ?int
    {
        if ($sorted->isEmpty()) {
            return null;
        }

        $index = (int) ceil($p * $sorted->count()) - 1;

        return $sorted->get(max(0, $index));
    }
}
