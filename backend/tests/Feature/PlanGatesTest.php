<?php

use App\Models\Monitor;
use App\Models\User;

function proUser(): User
{
    return User::factory()->create(['plan' => 'pro', 'pro_until' => now()->addMonth()]);
}

it('blocks a second monitor on the free plan', function () {
    $user = User::factory()->create();
    Monitor::factory()->for($user)->create();

    $this->actingAs($user)->postJson('/api/monitors', [
        'name' => 'Second', 'url' => 'https://example.com/mcp',
    ])->assertStatus(422)->assertJsonPath('upgrade_required', true);
});

it('allows multiple monitors on pro', function () {
    $user = proUser();
    Monitor::factory()->for($user)->create();

    $this->actingAs($user)->postJson('/api/monitors', [
        'name' => 'Second', 'url' => 'https://example.com/mcp',
    ])->assertCreated();
});

it('rejects a 1-minute interval on free but allows it on pro', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/monitors', [
        'name' => 'Fast', 'url' => 'https://example.com/mcp', 'interval_seconds' => 60,
    ])->assertUnprocessable();

    $this->actingAs(proUser())->postJson('/api/monitors', [
        'name' => 'Fast', 'url' => 'https://example.com/mcp', 'interval_seconds' => 60,
    ])->assertCreated();
});

it('rejects synthetic tool calls on free', function () {
    $this->actingAs(User::factory()->create())->postJson('/api/monitors', [
        'name' => 'Synth', 'url' => 'https://example.com/mcp',
        'synthetic_tool_name' => 'echo',
    ])->assertUnprocessable();
});

it('rejects slack channels on free but allows email', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/api/alert-channels', [
        'type' => 'slack', 'target' => 'https://hooks.slack.com/services/T/B/x',
    ])->assertStatus(422)->assertJsonPath('upgrade_required', true);

    $this->actingAs($user)->postJson('/api/alert-channels', [
        'type' => 'email', 'target' => 'alerts@example.com',
    ])->assertCreated();
});

it('reports plan limits in the auth payload', function () {
    $user = proUser();

    $this->actingAs($user)->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.plan', 'pro')
        ->assertJsonPath('data.user.limits.min_interval_seconds', 60)
        ->assertJsonPath('data.user.limits.synthetic_calls', true);
});
