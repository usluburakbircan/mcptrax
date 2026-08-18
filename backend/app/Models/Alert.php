<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'monitor_id',
        'kind',
        'opened_at',
        'resolved_at',
        'reason',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
