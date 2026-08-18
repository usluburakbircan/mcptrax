<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

/**
 * İşlenmiş Paddle bildirimi.
 *
 * Tek işi tekrarları elemek. Ayrıntı için migration'a bakın.
 */
class PaddleEvent extends Model
{
    protected $fillable = ['event_id', 'event_type', 'occurred_at', 'processed_at', 'outcome'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Bu olayı işlemek üzere yer ayır.
     *
     * Daha önce görülmüşse null döner ve olay ATLANIR.
     *
     * Kilit veritabanının UNIQUE kısıtı — "önce sor, sonra yaz" değil. Aynı
     * olayın iki kopyası aynı anda gelirse (Paddle'ın tekrarı ile ilk deneme
     * çakışabiliyor) ikisi de boş sonuç görür ve ikisi de işlerdi; kullanıcıya
     * iki kez kredi verilirdi. Burada ikincisi insert'te patlıyor.
     */
    public static function claim(string $eventId, string $eventType, ?string $occurredAt): ?self
    {
        try {
            return self::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'occurred_at' => $occurredAt,
            ]);
        } catch (QueryException $e) {
            // 23000 = integrity constraint violation (unique). Başka bir
            // veritabanı hatasını yutmuyoruz: o gerçek bir arıza.
            if (($e->errorInfo[0] ?? null) === '23000') {
                return null;
            }

            throw $e;
        }
    }

    /** İşleme bitti; sonucu kaydet. */
    public function finish(string $outcome): void
    {
        $this->forceFill([
            'processed_at' => now(),
            'outcome' => mb_substr($outcome, 0, 191),
        ])->save();
    }
}
