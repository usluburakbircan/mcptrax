<?php

namespace App\Services\Paddle;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Paddle'ın müşteri portalına giriş bileti üretir.
 *
 * NEDEN KENDİ İPTAL EKRANIMIZI YAZMIYORUZ: kart güncelleme PCI kapsamına
 * giriyor, iptal ise vergi/iade kayıtlarıyla birlikte yürüyor. İkisini de
 * Paddle'da bırakmak, kayıt sahibinin (merchant of record) Paddle olmasının
 * asıl faydası. Bizim tarafta tutulan tek şey, hangi kullanıcının hangi
 * `customer_id`'ye bağlı olduğu.
 *
 * OTURUM BİLETİ SAKLANMIYOR. Paddle bu URL'leri kısa ömürlü üretiyor ve URL'i
 * bilen herkes o müşterinin faturalarını görebiliyor. Veritabanına yazsaydık,
 * bir yedek dosyasına düşen satır aylar sonra hâlâ geçerli bir kapı olurdu.
 * Her tıklamada yeniden üretiliyor.
 */
class PaddlePortal
{
    /** Kullanıcı bir düğmeye basıp bekliyor; Paddle yanıt vermiyorsa uzun süre asılı kalmasın. */
    private const TIMEOUT_SECONDS = 10;

    /**
     * @return array{overview: string, cancel: ?string, payment_method: ?string}
     *
     * @throws RuntimeException Paddle'a ulaşılamadığında ya da beklenen alan gelmediğinde.
     */
    public function createSession(string $customerId, ?string $subscriptionId = null): array
    {
        $apiKey = (string) config('paddle.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        // Abonelik kimliği verilirse Paddle o aboneliğe özel derin linkleri
        // (iptal / kart güncelleme) de döndürüyor. Vermezsek yalnızca genel
        // bakış geliyor ve kullanıcı iptali portal içinde aramak zorunda
        // kalıyor.
        $payload = $subscriptionId ? ['subscription_ids' => [$subscriptionId]] : [];

        $response = Http::withToken($apiKey)
            ->timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            // GÖVDE ELDE KODLANIYOR. `->post($url, [])` boş diziyi JSON'a `[]`
            // diye yazıyor; Paddle gövdenin nesne olmasını şart koşuyor ve
            // "Expected: object, given: array" diyerek 400 dönüyor. Yani
            // aboneliği olmayan (yalnızca tek seferlik alım yapmış) her
            // kullanıcı portalı hiç açamıyordu. JSON_FORCE_OBJECT değil
            // `(object)` cast'i: ilki iç içe listeleri de nesneye çevirip
            // `subscription_ids`'i bozardı.
            ->withBody(json_encode((object) $payload, JSON_THROW_ON_ERROR), 'application/json')
            ->post(rtrim((string) config('paddle.api_base'), '/')."/customers/{$customerId}/portal-sessions");

        if ($response->failed()) {
            // Gövde loglanmıyor: Paddle hata yanıtlarında müşteri e-postası
            // gibi alanları geri yansıtabiliyor.
            Log::error('Paddle portal session failed', [
                'status' => $response->status(),
                'customer_id' => $customerId,
            ]);

            throw new RuntimeException('Paddle rejected the portal session request.');
        }

        $data = (array) $response->json('data', []);
        $overview = $data['urls']['general']['overview'] ?? null;

        if (! is_string($overview) || $overview === '') {
            throw new RuntimeException('Paddle returned no portal URL.');
        }

        // Derin linkler yalnızca abonelik kimliği gönderildiğinde ve o abonelik
        // hâlâ Paddle'da açıkken geliyor; yoksa genel bakış tek başına yeterli.
        $subscription = (array) ($data['urls']['subscriptions'][0] ?? []);

        return [
            'overview' => $overview,
            'cancel' => $this->stringOrNull($subscription['cancel_subscription'] ?? null),
            'payment_method' => $this->stringOrNull($subscription['update_subscription_payment_method'] ?? null),
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
