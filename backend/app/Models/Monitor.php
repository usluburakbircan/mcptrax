<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    /** @use HasFactory<\Database\Factories\MonitorFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UP = 'up';

    public const STATUS_DOWN = 'down';

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'url',
        'transport',
        'auth_header_name',
        'auth_header_value',
        'interval_seconds',
        'synthetic_tool_name',
        'synthetic_tool_args',
        'synthetic_expect_substring',
        'is_public',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'auth_header_value' => 'encrypted',
            'synthetic_tool_args' => 'array',
            'tool_names' => 'array',
            'is_public' => 'boolean',
            'next_check_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_status_change_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function rollups(): HasMany
    {
        return $this->hasMany(CheckRollup::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function openAlert(string $kind = 'down'): ?Alert
    {
        return $this->alerts()->where('kind', $kind)->whereNull('resolved_at')->latest('opened_at')->first();
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }
}
