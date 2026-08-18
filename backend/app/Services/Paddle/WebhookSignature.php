<?php

namespace App\Services\Paddle;

/**
 * Paddle webhook imzası.
 *
 * Başlık biçimi:  Paddle-Signature: ts=1671552777;h1=eb4d0dc8...
 * İmzalanan veri: "{ts}:{HAM GÖVDE}"  →  HMAC-SHA256, hex.
 *
 * Bildirim ucunun ASIL savunması bu sınıf. Başarısız olursa bildirim HİÇBİR
 * ŞEY açmamalı: doğrulanmamış bir gövdeye güvenmek, uydurma bir POST ile
 * kendine Premium açtırmak demek.
 */
class WebhookSignature
{
    /**
     * Zaman damgası toleransı (saniye).
     *
     * Tekrar saldırısına (replay) karşı: geçerli bir imzayı ele geçiren biri
     * onu sonsuza kadar yeniden gönderemesin. Beş dakika, Paddle'ın kendi
     * önerdiği bandın içinde ve sunucu saatlerindeki normal kaymayı tolere
     * ediyor. Not: idempotency asıl korumadır — bu, ona ek bir kat.
     */
    public const TOLERANCE_SECONDS = 300;

    /**
     * @param  string  $payload  HAM gövde. Yeniden kodlanmış JSON DEĞİL —
     *                           bir boşluk ya da anahtar sırası farkı imzayı
     *                           geçersiz kılar, çünkü HMAC bayt üzerinde.
     */
    public function verify(string $payload, ?string $header, ?string $secret, ?int $now = null): bool
    {
        if ($secret === null || $secret === '') {
            // Sır tanımlı değilse doğrulama YAPILAMAZ. "Doğrulanamadı" ile
            // "doğrulandı" arasındaki farkı silmemek için açıkça false.
            return false;
        }

        $parts = $this->parse($header);
        $ts = $parts['ts'] ?? null;
        $h1 = $parts['h1'] ?? null;

        if ($ts === null || $h1 === null || ! ctype_digit($ts)) {
            return false;
        }

        $now ??= time();

        if (abs($now - (int) $ts) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $ts.':'.$payload, $secret);

        // hash_equals: uzunluk-sabit karşılaştırma. Düz `===` ile yapılsaydı
        // yanıt süresi imzanın kaçıncı baytta ayrıldığını sızdırırdı.
        return hash_equals($expected, $h1);
    }

    /**
     * `ts=...;h1=...` → ['ts' => ..., 'h1' => ...]
     *
     * @return array<string, string>
     */
    private function parse(?string $header): array
    {
        if ($header === null || $header === '') {
            return [];
        }

        $out = [];

        foreach (explode(';', $header) as $piece) {
            // Yalnızca İLK '=' ayırıcı: imza değeri hex olsa da ileride
            // base64 taşıyan bir sürüm gelirse '=' dolgusu bozmasın.
            $pair = explode('=', trim($piece), 2);

            if (count($pair) === 2 && $pair[0] !== '') {
                $out[$pair[0]] = $pair[1];
            }
        }

        return $out;
    }
}
