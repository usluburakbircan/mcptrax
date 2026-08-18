<?php

use App\Models\Monitor;
use App\Probes\ProbeResult;
use App\Services\ProbeRecorder;

function successResult(array $overrides = []): ProbeResult
{
    return new ProbeResult(...array_merge([
        'ok' => true,
        'connectMs' => 120,
        'toolsListMs' => 40,
        'serverName' => 'Fake',
        'serverVersion' => '1.0.0',
        'protocolVersion' => '2025-06-18',
        'toolNames' => ['add', 'echo'],
        'toolsHash' => hash('sha256', 'v1'),
    ], $overrides));
}

function failureResult(): ProbeResult
{
    return new ProbeResult(
        ok: false,
        failedPhase: 'initialize',
        errorClass: 'RuntimeException',
        errorMessage: 'connection refused',
        connectMs: 10000,
    );
}

it('marks a pending monitor up on first success and stores the tools hash', function () {
    $monitor = Monitor::factory()->create();
    $recorder = app(ProbeRecorder::class);

    $recorder->record($monitor, successResult());

    $monitor->refresh();

    expect($monitor->status)->toBe(Monitor::STATUS_UP)
        ->and($monitor->tools_hash)->toBe(hash('sha256', 'v1'))
        ->and($monitor->tool_names)->toBe(['add', 'echo'])
        ->and($monitor->checks)->toHaveCount(1)
        ->and($monitor->alerts)->toHaveCount(0);
});

it('requires consecutive failures to reach the threshold before going down', function () {
    $monitor = Monitor::factory()->create(['failure_threshold' => 2]);
    $recorder = app(ProbeRecorder::class);

    $recorder->record($monitor, failureResult());
    $monitor->refresh();

    expect($monitor->status)->toBe(Monitor::STATUS_PENDING)
        ->and($monitor->consecutive_failures)->toBe(1)
        ->and($monitor->alerts)->toHaveCount(0);

    $recorder->record($monitor, failureResult());
    $monitor->refresh();

    expect($monitor->status)->toBe(Monitor::STATUS_DOWN)
        ->and($monitor->openAlert('down'))->not->toBeNull();
});

it('does not open a duplicate alert while one is already open', function () {
    $monitor = Monitor::factory()->create(['failure_threshold' => 1]);
    $recorder = app(ProbeRecorder::class);

    $recorder->record($monitor, failureResult());
    $recorder->record($monitor, failureResult());
    $recorder->record($monitor, failureResult());

    expect($monitor->alerts()->count())->toBe(1);
});

it('resolves the alert and recovers on success after downtime', function () {
    $monitor = Monitor::factory()->create(['failure_threshold' => 1]);
    $recorder = app(ProbeRecorder::class);

    $recorder->record($monitor, failureResult());
    $monitor->refresh();
    expect($monitor->status)->toBe(Monitor::STATUS_DOWN);

    $recorder->record($monitor, successResult());
    $monitor->refresh();

    expect($monitor->status)->toBe(Monitor::STATUS_UP)
        ->and($monitor->consecutive_failures)->toBe(0)
        ->and($monitor->openAlert('down'))->toBeNull()
        ->and($monitor->alerts()->whereNotNull('resolved_at')->count())->toBe(1);
});

it('opens a drift alert when the tools hash changes', function () {
    $monitor = Monitor::factory()->create();
    $recorder = app(ProbeRecorder::class);

    $recorder->record($monitor, successResult());
    $monitor->refresh();

    $recorder->record($monitor, successResult([
        'toolNames' => ['echo', 'multiply'],
        'toolsHash' => hash('sha256', 'v2'),
    ]));
    $monitor->refresh();

    $drift = $monitor->openAlert('drift');

    expect($monitor->status)->toBe(Monitor::STATUS_UP)
        ->and($drift)->not->toBeNull()
        ->and($drift->error_message)->toContain('added: multiply')
        ->and($drift->error_message)->toContain('removed: add')
        ->and($monitor->tools_hash)->toBe(hash('sha256', 'v2'));
});

it('does not flag drift on the very first successful check', function () {
    $monitor = Monitor::factory()->create();

    app(ProbeRecorder::class)->record($monitor, successResult());

    expect($monitor->checks()->first()->tools_drift)->toBeFalse()
        ->and($monitor->alerts()->count())->toBe(0);
});
