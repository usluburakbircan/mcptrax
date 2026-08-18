<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    use Prunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'monitor_id',
        'started_at',
        'ok',
        'failed_phase',
        'error_class',
        'error_message',
        'latency_ms',
        'connect_ms',
        'tools_list_ms',
        'tool_call_ms',
        'server_name',
        'server_version',
        'protocol_version',
        'tools_count',
        'tools_drift',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ok' => 'boolean',
            'tools_drift' => 'boolean',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Ham kontrol satırları saatlik rollup'lara özetlenir; ham veri plan
     * bazında budanır (Free 7 gün, Pro 30 gün). Status sayfaları rollup'tan
     * beslendiği için budama uptime geçmişini etkilemez.
     */
    public function prunable(): Builder
    {
        return static::where(function (Builder $query) {
            $query
                ->where('started_at', '<', now()->subDays(\App\Enums\Plan::Pro->retentionDays()))
                ->orWhere(function (Builder $free) {
                    $free
                        ->where('started_at', '<', now()->subDays(\App\Enums\Plan::Free->retentionDays()))
                        ->whereHas('monitor.user', function ($user) {
                            // Ham `plan` kolonu yeterli: süresi dolmuş pro'nun
                            // verisini erken silmek geri alınamaz; okuma
                            // tarafındaki plan() düşüşünden bilerek daha
                            // muhafazakâr davranıyoruz.
                            $user->where('plan', \App\Enums\Plan::Free->value);
                        });
                });
        });
    }
}
