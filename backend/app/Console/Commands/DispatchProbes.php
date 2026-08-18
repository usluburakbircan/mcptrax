<?php

namespace App\Console\Commands;

use App\Jobs\ProbeMonitorJob;
use App\Models\Monitor;
use Illuminate\Console\Command;

class DispatchProbes extends Command
{
    protected $signature = 'probes:dispatch';

    protected $description = 'Queue a probe job for every monitor whose next check is due';

    public function handle(): int
    {
        $dispatched = 0;

        Monitor::query()
            ->whereNull('paused_at')
            ->where(function ($query) {
                $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
            })
            ->orderBy('next_check_at')
            ->each(function (Monitor $monitor) use (&$dispatched) {
                // next_check_at önce ileri alınır ki komut üst üste çalışsa
                // bile aynı monitör iki kez kuyruğa girmesin.
                $monitor->forceFill([
                    'next_check_at' => now()->addSeconds($monitor->interval_seconds),
                ])->save();

                ProbeMonitorJob::dispatch($monitor->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} probe job(s).");

        return self::SUCCESS;
    }
}
