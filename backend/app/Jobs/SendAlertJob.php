<?php

namespace App\Jobs;

use App\Mail\AlertMail;
use App\Models\Alert;
use App\Models\AlertChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(
        public int $alertId,
        public bool $opened,
    ) {
    }

    public function handle(): void
    {
        $alert = Alert::with('monitor.user.alertChannels')->find($this->alertId);

        if ($alert === null) {
            return;
        }

        $channels = $alert->monitor->user->alertChannels->where('is_active', true);

        // Hiç kanal tanımlanmamışsa hesap e-postası varsayılan kanaldır;
        // "uyarı kurulumunu unuttum, kesintiyi kaçırdım" durumu yaşanmamalı.
        if ($channels->isEmpty()) {
            $this->safely(fn () => Mail::to($alert->monitor->user->email)->send(new AlertMail($alert, $this->opened)));

            return;
        }

        foreach ($channels as $channel) {
            $this->safely(fn () => match ($channel->type) {
                'email' => Mail::to($channel->target)->send(new AlertMail($alert, $this->opened)),
                'slack' => $this->sendSlack($channel, $alert),
                'webhook' => $this->sendWebhook($channel, $alert),
                default => null,
            });
        }
    }

    protected function sendSlack(AlertChannel $channel, Alert $alert): void
    {
        $monitor = $alert->monitor;

        $emoji = $alert->kind === 'drift' ? '⚠️' : ($this->opened ? '🔴' : '🟢');
        $headline = $alert->kind === 'drift'
            ? "Tools changed on *{$monitor->name}*"
            : ($this->opened ? "*{$monitor->name}* is DOWN" : "*{$monitor->name}* recovered");

        $detail = $alert->error_message ? "\n> ".mb_substr($alert->error_message, 0, 500) : '';

        Http::timeout(10)->post($channel->target, [
            'text' => "{$emoji} {$headline}\n`{$monitor->url}`{$detail}",
        ])->throw();
    }

    protected function sendWebhook(AlertChannel $channel, Alert $alert): void
    {
        $monitor = $alert->monitor;

        Http::timeout(10)->post($channel->target, [
            'event' => $this->opened ? 'alert.opened' : 'alert.resolved',
            'kind' => $alert->kind,
            'monitor' => [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'url' => $monitor->url,
                'status' => $monitor->status,
            ],
            'reason' => $alert->reason,
            'error_message' => $alert->error_message,
            'opened_at' => $alert->opened_at->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ])->throw();
    }

    /**
     * Kanallardan biri hata verirse diğerleri yine denenmelidir.
     */
    protected function safely(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning('alert channel delivery failed', [
                'alert_id' => $this->alertId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
