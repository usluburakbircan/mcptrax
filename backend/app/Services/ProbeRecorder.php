<?php

namespace App\Services;

use App\Jobs\SendAlertJob;
use App\Models\Alert;
use App\Models\Check;
use App\Models\Monitor;
use App\Probes\ProbeResult;
use Illuminate\Support\Facades\Log;

/**
 * Bir prob sonucunu kalıcılaştırır ve monitörün durum makinesini işletir:
 * ardışık N hatada down + alert açılır, ilk başarıda up + alert kapanır.
 * Uyarı bildirimleri (mail/Slack) burada değil, açılan Alert üzerinden
 * kuyruklanır — böylece de-dup incident satırıyla kendiliğinden sağlanır.
 */
class ProbeRecorder
{
    public function record(Monitor $monitor, ProbeResult $result): Check
    {
        $drift = $result->ok
            && $monitor->tools_hash !== null
            && $result->toolsHash !== null
            && $monitor->tools_hash !== $result->toolsHash;

        $check = $monitor->checks()->create([
            'started_at' => now(),
            'ok' => $result->ok,
            'failed_phase' => $result->failedPhase,
            'error_class' => $result->errorClass,
            'error_message' => $result->errorMessage,
            'latency_ms' => $result->totalMs(),
            'connect_ms' => $result->connectMs,
            'tools_list_ms' => $result->toolsListMs,
            'tool_call_ms' => $result->toolCallMs,
            'server_name' => $result->serverName,
            'server_version' => $result->serverVersion,
            'protocol_version' => $result->protocolVersion,
            'tools_count' => $result->ok ? count($result->toolNames) : null,
            'tools_drift' => $drift,
        ]);

        $monitor->last_checked_at = now();

        if ($result->ok) {
            $this->handleSuccess($monitor, $result, $drift);
        } else {
            $this->handleFailure($monitor, $result);
        }

        $monitor->save();

        return $check;
    }

    protected function handleSuccess(Monitor $monitor, ProbeResult $result, bool $drift): void
    {
        $monitor->consecutive_failures = 0;

        if ($drift) {
            $previous = $monitor->tool_names ?? [];
            $added = array_values(array_diff($result->toolNames, $previous));
            $removed = array_values(array_diff($previous, $result->toolNames));

            $this->openAlert($monitor, 'drift', 'tools_changed', $this->driftMessage($added, $removed));
        }

        $monitor->tools_hash = $result->toolsHash;
        $monitor->tool_names = $result->toolNames;

        if ($monitor->status !== Monitor::STATUS_UP) {
            $monitor->status = Monitor::STATUS_UP;
            $monitor->last_status_change_at = now();

            $this->resolveAlert($monitor, 'down');
        }
    }

    protected function handleFailure(Monitor $monitor, ProbeResult $result): void
    {
        $monitor->consecutive_failures++;

        $shouldGoDown = $monitor->status !== Monitor::STATUS_DOWN
            && $monitor->consecutive_failures >= $monitor->failure_threshold;

        if ($shouldGoDown) {
            $monitor->status = Monitor::STATUS_DOWN;
            $monitor->last_status_change_at = now();

            $this->openAlert($monitor, 'down', $result->failedPhase, $result->errorMessage);
        }
    }

    protected function openAlert(Monitor $monitor, string $kind, ?string $reason, ?string $message): void
    {
        if ($monitor->openAlert($kind) !== null) {
            return;
        }

        $alert = $monitor->alerts()->create([
            'kind' => $kind,
            'opened_at' => now(),
            'reason' => $reason,
            'error_message' => $message,
        ]);

        $this->notify($alert, opened: true);
    }

    protected function resolveAlert(Monitor $monitor, string $kind): void
    {
        $alert = $monitor->openAlert($kind);

        if ($alert === null) {
            return;
        }

        $alert->update(['resolved_at' => now()]);

        $this->notify($alert, opened: false);
    }

    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     */
    protected function driftMessage(array $added, array $removed): string
    {
        $parts = [];

        if ($added !== []) {
            $parts[] = 'added: '.implode(', ', $added);
        }

        if ($removed !== []) {
            $parts[] = 'removed: '.implode(', ', $removed);
        }

        return $parts === [] ? 'Tool schemas changed.' : 'Tools changed — '.implode('; ', $parts);
    }

    protected function notify(Alert $alert, bool $opened): void
    {
        SendAlertJob::dispatch($alert->id, $opened);

        Log::info('monitor alert', [
            'monitor_id' => $alert->monitor_id,
            'kind' => $alert->kind,
            'event' => $opened ? 'opened' : 'resolved',
            'reason' => $alert->reason,
        ]);
    }
}
