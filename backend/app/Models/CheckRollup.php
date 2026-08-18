<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckRollup extends Model
{
    protected $fillable = [
        'monitor_id',
        'hour_start',
        'checks_count',
        'failures_count',
        'p50_ms',
        'p95_ms',
        'max_ms',
    ];

    protected function casts(): array
    {
        return [
            'hour_start' => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
