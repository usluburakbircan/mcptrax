<?php

use App\Models\Monitor;
use App\Models\User;

it('registers, logs in and manages monitors end to end', function () {
    $register = $this->postJson('/api/auth/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret-password',
    ])->assertCreated();

    $token = $register->json('data.token');
    $headers = ['Authorization' => "Bearer {$token}"];

    $create = $this->postJson('/api/monitors', [
        'name' => 'My MCP Server',
        'url' => 'https://mcp.example.com/mcp',
        'interval_seconds' => 900,
    ], $headers)->assertCreated();

    $monitorId = $create->json('data.monitor.id');

    $this->getJson('/api/monitors', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data.monitors');

    $this->postJson("/api/monitors/{$monitorId}/pause", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.monitor.paused', true);

    $this->postJson("/api/monitors/{$monitorId}/resume", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.monitor.paused', false);

    $this->deleteJson("/api/monitors/{$monitorId}", [], $headers)->assertOk();

    expect(Monitor::count())->toBe(0);
});

it('hides other users monitors', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $monitor = Monitor::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->getJson("/api/monitors/{$monitor->id}")
        ->assertNotFound();
});

it('rejects unauthenticated monitor access', function () {
    $this->getJson('/api/monitors')->assertUnauthorized();
});
