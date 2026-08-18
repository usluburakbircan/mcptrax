<?php

use App\Probes\UrlGuard;
use Tests\Support\FakesMcp;

uses(FakesMcp::class);

it('returns a full report for a healthy server', function () {
    $this->fakeMcpServer(tools: [
        ['name' => 'echo', 'description' => 'Echoes back the given message.', 'inputSchema' => ['type' => 'object']],
    ]);

    $this->postJson('/api/check', ['url' => 'https://example.test/mcp'])
        ->assertOk()
        ->assertJsonPath('data.report.ok', true)
        ->assertJsonPath('data.report.server_name', 'Fake Server')
        ->assertJsonPath('data.report.protocol_version', '2025-06-18')
        ->assertJsonPath('data.report.tools.0.name', 'echo')
        ->assertJsonPath('data.report.tools.0.description', 'Echoes back the given message.');
});

it('reports the failed phase for a broken server', function () {
    $this->fakeMcpServerDown();

    $this->postJson('/api/check', ['url' => 'https://example.test/mcp'])
        ->assertOk()
        ->assertJsonPath('data.report.ok', false)
        ->assertJsonPath('data.report.failed_phase', 'initialize');
});

it('rejects invalid urls', function () {
    $this->postJson('/api/check', ['url' => 'not-a-url'])->assertUnprocessable();
    $this->postJson('/api/check', ['url' => 'ftp://example.com/mcp'])->assertUnprocessable();
});

it('enforces the rate limit', function () {
    $this->fakeMcpServer();

    foreach (range(1, 3) as $i) {
        $this->postJson('/api/check', ['url' => 'https://example.test/mcp'])->assertOk();
    }

    $this->postJson('/api/check', ['url' => 'https://example.test/mcp'])->assertStatus(429);
});

it('guards against private and loopback targets', function () {
    expect(UrlGuard::isSafe('http://127.0.0.1/mcp'))->toBeFalse()
        ->and(UrlGuard::isSafe('http://10.0.0.5/mcp'))->toBeFalse()
        ->and(UrlGuard::isSafe('http://192.168.1.1/mcp'))->toBeFalse()
        ->and(UrlGuard::isSafe('http://169.254.169.254/latest/meta-data'))->toBeFalse()
        ->and(UrlGuard::isSafe('http://localhost/mcp'))->toBeFalse()
        ->and(UrlGuard::isSafe('http://foo.internal/mcp'))->toBeFalse()
        ->and(UrlGuard::isSafe('ftp://example.com/mcp'))->toBeFalse()
        ->and(UrlGuard::isSafe('https://mcp.example-service.com/mcp'))->toBeTrue();
});

it('manages alert channels over the api', function () {
    $user = \App\Models\User::factory()->create(['plan' => 'pro', 'pro_until' => now()->addMonth()]);

    $this->actingAs($user)->postJson('/api/alert-channels', [
        'type' => 'slack',
        'target' => 'https://hooks.slack.com/services/T/B/x',
    ])->assertCreated();

    $this->actingAs($user)->postJson('/api/alert-channels', [
        'type' => 'email',
        'target' => 'not-an-email',
    ])->assertUnprocessable();

    $this->actingAs($user)->postJson('/api/alert-channels', [
        'type' => 'webhook',
        'target' => 'http://insecure.example.com/hook',
    ])->assertUnprocessable();

    $this->actingAs($user)->getJson('/api/alert-channels')
        ->assertOk()
        ->assertJsonCount(1, 'data.channels');
});
