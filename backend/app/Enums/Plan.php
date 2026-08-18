<?php

namespace App\Enums;

/**
 * Plan kademeleri ve her kademenin sınırları.
 *
 * Sınırların TEK kaynağı burası: controller doğrulamaları, pruning ve
 * frontend'e giden kullanıcı yükü hep bu enum'dan okur. İki yerde sınır
 * tanımlamak, birini güncelleyip diğerini unutmak demek.
 */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Pro => 'Pro',
        };
    }

    public function maxMonitors(): int
    {
        return match ($this) {
            self::Free => 1,
            self::Pro => 50,
        };
    }

    public function minIntervalSeconds(): int
    {
        return match ($this) {
            self::Free => 900,
            self::Pro => 60,
        };
    }

    public function syntheticCalls(): bool
    {
        return $this === self::Pro;
    }

    /** Slack ve generic webhook kanalları; e-posta her planda var. */
    public function nonEmailChannels(): bool
    {
        return $this === self::Pro;
    }

    public function retentionDays(): int
    {
        return match ($this) {
            self::Free => 7,
            self::Pro => 30,
        };
    }
}
