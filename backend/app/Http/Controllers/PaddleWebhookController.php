<?php

namespace App\Http\Controllers;

use App\Models\PaddleEvent;
use App\Services\Paddle\PaddleEntitlements;
use App\Services\Paddle\WebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paddle bildirim ucu.
 *
 * Üç kat, sırayla:
 *   1. İMZA — doğrulanmayan gövde hiçbir şey yapmaz (401).
 *   2. TEKRAR ELEME — aynı `event_id` ikinci kez işlenmez.
 *   3. DAĞITIM — olay türüne göre hak uygulanır.
 *
 * Paddle'ın imzası dokümante ve doğrulanabilir olduğu için gövde
 * doğrulandıktan SONRA güvenilir kabul ediliyor — ama hangi ürünün ne
 * verdiği yine BİZİM haritamızdan (config/paddle.php) okunuyor, gövdeden
 * değil. "Ödendi" bilgisi tek başına hiçbir hak açmıyor.
 *
 * YANIT KODLARI bilinçli seçilmiş:
 *   - imza geçersiz → 401. Tekrar göndermesini İSTİYORUZ değil; bu bir
 *     yapılandırma hatası ya da saldırı, ikisi de sessizce yutulmamalı.
 *   - işlenemeyen olay → 200. Tanımadığımız bir olay türü için Paddle'ın
 *     sonsuza kadar tekrar denemesinin bir faydası yok.
 *   - işleme sırasında istisna → 500 ve tekrar eleme KAYDI SİLİNİR, yani
 *     Paddle'ın tekrarı yeniden işlenebilir. Kayıt bırakılsaydı tekrar
 *     "zaten işlendi" sayılır ve hak kalıcı olarak kaybolurdu.
 */
class PaddleWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        WebhookSignature $signature,
        PaddleEntitlements $entitlements,
    ): JsonResponse
    {
        // getContent(): HAM gövde. $request->all() üzerinden yeniden
        // kodlanmış JSON kullanılsaydı bir boşluk farkı bile imzayı bozardı.
        $raw = $request->getContent();

        $valid = $signature->verify(
            $raw,
            $request->header('Paddle-Signature'),
            config('paddle.webhook_secret'),
        );

        if (! $valid) {
            Log::warning('Paddle webhook signature rejected', [
                'has_secret' => ! empty(config('paddle.webhook_secret')),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $body = json_decode($raw, true);

        if (! is_array($body)) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        $eventId = (string) ($body['event_id'] ?? '');
        $eventType = (string) ($body['event_type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            return response()->json(['error' => 'missing event id or type'], 400);
        }

        $event = PaddleEvent::claim($eventId, $eventType, $body['occurred_at'] ?? null);

        if ($event === null) {
            // Zaten işlenmiş. 200 döndürmek şart: 4xx/5xx dönseydik Paddle
            // aynı olayı tekrar tekrar göndermeye devam ederdi.
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        /*
         * Hak uygulama.
         *
         * HATA DURUMUNDA KAYIT SİLİNİYOR — ve bu satır kritik.
         *
         * Kilit `event_id` üzerindeki UNIQUE index olduğu için, yarım kalmış
         * bir satır bırakılsaydı Paddle'ın tekrarı "zaten işlenmiş" sayılıp
         * elenir ve hak KALICI olarak kaybolurdu: ödemesi alınmış ama planı
         * açılmamış bir kullanıcı, ikinci bir şansı olmadan geride kalırdı.
         * Kaydı silmek kilidi geri veriyor; 500 yanıtı da Paddle'a tekrar
         * denemesini söylüyor.
         *
         * İstisnayı yutup 200 dönmek aynı sessiz kaybı üretirdi, o yüzden
         * yeniden fırlatılıyor.
         */
        try {
            $outcome = $entitlements->apply($body);
        } catch (\Throwable $e) {
            $event->delete();

            Log::error('Paddle webhook failed; claim released for retry', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        Log::info('Paddle webhook applied', [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'outcome' => $outcome,
        ]);

        $event->finish($outcome);

        return response()->json(['received' => true]);
    }
}
