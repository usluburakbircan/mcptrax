<?php

use App\Jobs\ProbeMonitorJob;
use App\Models\Check;
use App\Models\CheckRollup;
use App\Models\Monitor;
use Illuminate\Support\Facades\Queue;

it('dispatches probe jobs only for due, unpaused monitors and advances next_check_at', function () {
    Queue::fake();

    $due = Monitor::factory()->create(['next_check_at' => now()->subMinute()]);
    $fresh = Monitor::factory()->create(['next_check_at' => null]);
    $notDue = Monitor::factory()->create(['next_check_at' => now()->addMinutes(10)]);
    $paused = Monitor::factory()->create(['next_check_at' => now()->subMinute(), 'paused_at' => now()]);

    $this->artisan('probes:dispatch')->assertSuccessful();

    Queue::assertPushed(ProbeMonitorJob::class, 2);
    Queue::assertPushed(fn (ProbeMonitorJob $job) => $job->monitorId === $due->id);
    Queue::assertPushed(fn (ProbeMonitorJob $job) => $job->monitorId === $fresh->id);
    Queue::assertNotPushed(fn (ProbeMonitorJob $job) => $job->monitorId === $notDue->id);
    Queue::assertNotPushed(fn (ProbeMonitorJob $job) => $job->monitorId === $paused->id);

    expect($due->fresh()->next_check_at->timestamp)
        ->toBeGreaterThan(now()->timestamp);
});

it('computes hourly rollups with latency percentiles', function () {
    $monitor = Monitor::factory()->create();
    $hour = now()->subHour()->startOfHour();

    foreach ([100, 200, 300, 400, 1000] as $i => $latency) {
        Check::create([
            'monitor_id' => $monitor->id,
            'started_at' => $hour->copy()->addMinutes($i),
            'ok' => $latency < 1000,
            'latency_ms' => $latency,
        ]);
    }

    $this->artisan('rollups:compute')->assertSuccessful();

    $rollup = CheckRollup::where('monitor_id', $monitor->id)->first();

    expect($rollup)->not->toBeNull()
        ->and($rollup->checks_count)->toBe(5)
        ->and($rollup->failures_count)->toBe(1)
        ->and($rollup->p50_ms)->toBe(300)
        ->and($rollup->p95_ms)->toBe(1000)
        ->and($rollup->max_ms)->toBe(1000);
});

it('prunes checks older than the retention window', function () {
    $monitor = Monitor::factory()->create();

    Check::create(['monitor_id' => $monitor->id, 'started_at' => now()->subDays(40), 'ok' => true]);
    Check::create(['monitor_id' => $monitor->id, 'started_at' => now()->subDays(5), 'ok' => true]);

    $this->artisan('model:prune', ['--model' => [Check::class]])->assertSuccessful();

    expect(Check::count())->toBe(1);
});
