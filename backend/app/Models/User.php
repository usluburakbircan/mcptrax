<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pro_until' => 'datetime',
        ];
    }

    /**
     * GEÇERLİ plan. Ham kolon değil: süresi dolmuş bir abonelik Free
     * davranmalı — düşürme işini zamanlanmış göreve bırakmak yerine okuma
     * anında karar veriliyor; görevin çalışmaması erişimi uzatamaz.
     */
    public function plan(): Plan
    {
        // Ham attribute okuması: getAttribute('plan') kolon hydrate
        // edilmemişse (taze factory modeli) ilişki çözümlemeye düşer ve bu
        // metodu yeniden çağırıp sonsuz özyineleme yaratır.
        $plan = Plan::tryFrom((string) ($this->attributes['plan'] ?? '')) ?? Plan::Free;

        if ($plan !== Plan::Free && ($this->pro_until === null || $this->pro_until->isPast())) {
            return Plan::Free;
        }

        return $plan;
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function alertChannels(): HasMany
    {
        return $this->hasMany(AlertChannel::class);
    }
}
