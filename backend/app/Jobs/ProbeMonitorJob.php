<?php

namespace App\Jobs;

use App\Models\Monitor;
use App\Probes\McpProbe;
use App\Services\ProbeRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProbeMonitorJob implements ShouldQueue
{
    use Queueable;

    /**
     * Hata zaten alan mantığı: kuyruk retry'ı yanlış pozitif "up" aralıkları
     * yaratır, bu yüzden tek deneme.
     */
    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $monitorId)
    {
        $this->onQueue('probes');
    }

    public function handle(McpProbe $probe, ProbeRecorder $recorder): void
    {
        $monitor = Monitor::find($this->monitorId);

        if ($monitor === null || $monitor->isPaused()) {
            return;
        }

        $recorder->record($monitor, $probe->run($monitor));
    }
}
