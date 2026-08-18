<?php

use App\Models\CheckRollup;
use App\Models\Monitor;

it('serves a public status page with uptime and daily summary', function () {
    $monitor = Monitor::factory()->create([
        'is_public' => true,
        'slug' => 'public-demo',
        'status' => Monitor::STATUS_UP,
    ]);

    CheckRollup::create([
        'monitor_id' => $monitor->id,
        'hour_start' => now()->subHours(2)->startOfHour(),
        'checks_count' => 60,
        'failures_count' => 0,
        'p50_ms' => 120,
        'p95_ms' => 300,
        'max_ms' => 400,
    ]);
    CheckRollup::create([
        'monitor_id' => $monitor->id,
        'hour_start' => now()->subDay()->startOfHour(),
        'checks_count' => 40,
        'failures_count' => 10,
        'p50_ms' => 200,
        'p95_ms' => 900,
        'max_ms' => 1500,
    ]);

    $this->getJson('/api/status/public-demo')
        ->assertOk()
        ->assertJsonPath('data.name', $monitor->name)
        ->assertJsonPath('data.status', 'up')
        ->assertJsonPath('data.uptime_90d', 90)
        ->assertJsonCount(2, 'data.days');
});

it('hides private monitors and unknown slugs', function () {
    Monitor::factory()->create(['is_public' => false, 'slug' => 'private-one']);

    $this->getJson('/api/status/private-one')->assertNotFound();
    $this->getJson('/api/status/nope')->assertNotFound();
});

it('does not leak error details on the public page', function () {
    $monitor = Monitor::factory()->create(['is_public' => true, 'slug' => 'leak-check']);

    $response = $this->getJson('/api/status/leak-check')->assertOk();

    expect($response->json('data'))->not->toHaveKey('error_message');
});
