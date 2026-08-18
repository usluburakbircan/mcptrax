<?php

use App\Enums\Plan;
use App\Models\PaddleEvent;
use App\Models\User;

function paddlePayload(User $user, string $status = 'active', ?string $priceId = null, string $eventId = 'evt_1'): array
{
    return [
        'event_id' => $eventId,
        'event_type' => 'subscription.activated',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'id' => 'sub_123',
            'status' => $status,
            'customer_id' => 'ctm_123',
            'custom_data' => ['user_id' => $user->id],
            'current_billing_period' => [
                'starts_at' => now()->toIso8601String(),
                'ends_at' => now()->addMonth()->toIso8601String(),
            ],
            'items' => [
                ['price' => ['id' => $priceId ?? config('paddle.prices.pro_monthly.id')]],
            ],
        ],
    ];
}

function signedPost($test, array $payload, ?string $secret = null)
{
    $raw = json_encode($payload);
    $ts = time();
    $h1 = hash_hmac('sha256', $ts.':'.$raw, $secret ?? config('paddle.webhook_secret'));

    return $test->call('POST', '/api/paddle/webhook', [], [], [], [
        'HTTP_Paddle-Signature' => "ts={$ts};h1={$h1}",
        'CONTENT_TYPE' => 'application/json',
    ], $raw);
}

beforeEach(function () {
    config(['paddle.webhook_secret' => 'test-secret']);
});

it('rejects an unsigned webhook', function () {
    $this->postJson('/api/paddle/webhook', ['event_id' => 'evt_x', 'event_type' => 'subscription.activated'])
        ->assertStatus(401);
});

it('rejects a webhook signed with the wrong secret', function () {
    $user = User::factory()->create();

    signedPost($this, paddlePayload($user), secret: 'wrong-secret')->assertStatus(401);
});

it('activates pro on a valid subscription webhook', function () {
    $user = User::factory()->create();

    signedPost($this, paddlePayload($user))->assertOk();

    $user->refresh();

    expect($user->plan())->toBe(Plan::Pro)
        ->and($user->pro_until->isFuture())->toBeTrue()
        ->and($user->paddle_customer_id)->toBe('ctm_123')
        ->and($user->paddle_subscription_id)->toBe('sub_123');
});

it('ignores duplicate events', function () {
    $user = User::factory()->create();

    signedPost($this, paddlePayload($user))->assertOk();
    $response = signedPost($this, paddlePayload($user));

    $response->assertOk()->assertJsonPath('duplicate', true);
    expect(PaddleEvent::count())->toBe(1);
});

it('grants nothing for an unknown price id', function () {
    $user = User::factory()->create();

    signedPost($this, paddlePayload($user, priceId: 'pri_unknown'))->assertOk();

    expect($user->refresh()->plan())->toBe(Plan::Free);
});

it('keeps access until period end after cancellation', function () {
    $user = User::factory()->create();

    signedPost($this, paddlePayload($user))->assertOk();

    $cancel = paddlePayload($user, status: 'canceled', eventId: 'evt_2');
    $cancel['event_type'] = 'subscription.canceled';
    signedPost($this, $cancel)->assertOk();

    // İptal pro_until'i değiştirmez; dönem sonuna kadar Pro kalır.
    expect($user->refresh()->plan())->toBe(Plan::Pro);
});

it('treats an expired subscription as free', function () {
    $user = User::factory()->create([
        'plan' => 'pro',
        'pro_until' => now()->subDay(),
    ]);

    expect($user->plan())->toBe(Plan::Free);
});
