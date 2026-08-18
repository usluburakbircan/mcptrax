<?php

use App\Jobs\SendAlertJob;
use App\Mail\AlertMail;
use App\Models\Alert;
use App\Models\AlertChannel;
use App\Models\Monitor;
use App\Models\User;
use App\Probes\ProbeResult;
use App\Services\ProbeRecorder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function makeAlert(User $user, string $kind = 'down'): Alert
{
    $monitor = Monitor::factory()->for($user)->create(['status' => Monitor::STATUS_DOWN]);

    return $monitor->alerts()->create([
        'kind' => $kind,
        'opened_at' => now(),
        'reason' => 'initialize',
        'error_message' => 'connection refused',
    ]);
}

it('queues an alert job when a monitor goes down', function () {
    Queue::fake([SendAlertJob::class]);

    $monitor = Monitor::factory()->create(['failure_threshold' => 1]);

    app(ProbeRecorder::class)->record($monitor, new ProbeResult(
        ok: false, failedPhase: 'initialize', errorClass: 'RuntimeException', errorMessage: 'refused',
    ));

    Queue::assertPushed(SendAlertJob::class, fn ($job) => $job->opened === true);
});

it('falls back to the account email when no channels are configured', function () {
    Mail::fake();

    $user = User::factory()->create(['email' => 'owner@example.com']);
    $alert = makeAlert($user);

    (new SendAlertJob($alert->id, opened: true))->handle();

    Mail::assertSent(AlertMail::class, fn (AlertMail $mail) => $mail->hasTo('owner@example.com'));
});

it('delivers to every active channel and skips inactive ones', function () {
    Mail::fake();
    Http::fake(['*' => Http::response('ok')]);

    $user = User::factory()->create();
    AlertChannel::create(['user_id' => $user->id, 'type' => 'email', 'target' => 'alerts@example.com', 'is_active' => true]);
    AlertChannel::create(['user_id' => $user->id, 'type' => 'slack', 'target' => 'https://hooks.slack.com/services/T/B/x', 'is_active' => true]);
    AlertChannel::create(['user_id' => $user->id, 'type' => 'webhook', 'target' => 'https://example.com/hook', 'is_active' => true]);
    AlertChannel::create(['user_id' => $user->id, 'type' => 'email', 'target' => 'inactive@example.com', 'is_active' => false]);

    $alert = makeAlert($user);

    (new SendAlertJob($alert->id, opened: true))->handle();

    Mail::assertSent(AlertMail::class, fn (AlertMail $mail) => $mail->hasTo('alerts@example.com'));
    Mail::assertNotSent(AlertMail::class, fn (AlertMail $mail) => $mail->hasTo('inactive@example.com'));

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/T/B/x'
        && str_contains($request->body(), 'DOWN'));
    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook'
        && $request['event'] === 'alert.opened'
        && $request['kind'] === 'down');
});

it('keeps delivering to remaining channels when one fails', function () {
    Mail::fake();
    Http::fake([
        'hooks.slack.com/*' => Http::response('error', 500),
        'example.com/*' => Http::response('ok'),
    ]);

    $user = User::factory()->create();
    AlertChannel::create(['user_id' => $user->id, 'type' => 'slack', 'target' => 'https://hooks.slack.com/services/T/B/x', 'is_active' => true]);
    AlertChannel::create(['user_id' => $user->id, 'type' => 'webhook', 'target' => 'https://example.com/hook', 'is_active' => true]);

    $alert = makeAlert($user);

    (new SendAlertJob($alert->id, opened: true))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook');
});

it('renders recovery and drift emails', function () {
    $user = User::factory()->create();

    $down = makeAlert($user);
    $down->update(['resolved_at' => now()->addMinutes(5)]);
    $recoveryHtml = (new AlertMail($down, opened: false))->render();
    expect($recoveryHtml)->toContain('recovered');

    $drift = makeAlert($user, kind: 'drift');
    $drift->update(['error_message' => 'Tools changed — added: multiply; removed: add']);
    $driftHtml = (new AlertMail($drift->fresh(), opened: true))->render();
    expect($driftHtml)->toContain('Tools changed')
        ->and($driftHtml)->toContain('added: multiply');
});
