<?php

namespace App\Services\Paddle;

use App\Enums\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Paddle bildirimini hakka çeviren tek nokta.
 *
 * `pro_until` TÜRETİLEN değil YANSITILAN alan: süreyi toplamıyoruz,
 * Paddle'ın söylediği dönem bitişini (`current_billing_period.ends_at`)
 * kopyalıyoruz. Aynı bildirim iki kez gelse sonuç değişmez (idempotent),
 * bildirimlerin sırası bozulsa da tarih Paddle'ın kaydından ayrışamaz.
 *
 * Hangi price ID'nin ne açtığı gövdeden değil `config/paddle.php`
 * haritasından okunur; haritada olmayan ID hiçbir şey açmaz.
 */
class PaddleEntitlements
{
    /**
     * Erişim VEREN abonelik durumları. `past_due` bilerek içeride: Paddle
     * kartı birkaç gün yeniden dener; o pencerede kullanıcının monitörlerini
     * durdurmak, kendiliğinden düzelen bir sorun için müşteri kaybetmek olur.
     */
    private const GRANTING_STATUSES = ['active', 'trialing', 'past_due'];

    /**
     * @param  array<string, mixed>  $body  Doğrulanmış webhook gövdesi.
     * @return string `paddle_events.outcome` alanına yazılan sonuç.
     */
    public function apply(array $body): string
    {
        $type = (string) ($body['event_type'] ?? '');
        $data = (array) ($body['data'] ?? []);

        return match ($type) {
            'subscription.created',
            'subscription.updated',
            'subscription.activated',
            'subscription.canceled',
            'subscription.past_due',
            'subscription.paused',
            'subscription.resumed',
            'subscription.trialing' => $this->syncSubscription($data),

            // Tek ürünümüz abonelik; yenileme işlemleri abonelik olaylarının
            // sahasında. Burada da hak verseydik yenileme başına iki kez
            // uygulanırdı.
            'transaction.completed' => $this->recordCustomer($data),

            default => "ignored: {$type}",
        };
    }

    /**
     * Aboneliğin mevcut halini kullanıcıya yansıtır. Olay türü ne olursa
     * olsun tek yol: `status` + `current_billing_period` okunur. Olay türü
     * başına ayrı mantık, sırasız `updated` bildirimlerinde farklı sonuçlar
     * üretirdi.
     */
    private function syncSubscription(array $data): string
    {
        $user = $this->resolveUser($data);

        if ($user === null) {
            return 'no matching user';
        }

        $plan = $this->planFor($data);

        if ($plan === null) {
            // Haritada olmayan fiyat. Sessizce Free'ye düşürmüyoruz — yanlış
            // yapılandırılmış bir price ID yüzünden ödeme yapan kullanıcının
            // hakkını almak, hatanın en pahalı biçimi olurdu.
            Log::warning('Paddle subscription had no known price', [
                'subscription' => $data['id'] ?? null,
                'user_id' => $user->id,
            ]);

            return 'unknown price; left unchanged';
        }

        $status = (string) ($data['status'] ?? '');
        $endsAt = $this->periodEnd($data);

        $user->paddle_customer_id = (string) ($data['customer_id'] ?? $user->paddle_customer_id);
        $user->paddle_subscription_id = (string) ($data['id'] ?? $user->paddle_subscription_id);

        if (in_array($status, self::GRANTING_STATUSES, true) && $endsAt !== null) {
            $user->plan = $plan->value;
            $user->pro_until = $endsAt;
        }

        /*
         * İptal/durdurmada `pro_until` DEĞİŞMİYOR: kullanıcı ödediği dönemin
         * sonuna kadar erişimini korur. Düşürme işi zamanlanmış göreve de
         * bırakılmıyor — `User::plan()` süresi geçmiş aboneliği zaten Free
         * döndürür; tarih geldiğinde erişim kendiliğinden biter.
         */

        $user->save();

        return sprintf('%s → %s (until %s)', $status, $plan->value, $user->pro_until?->toDateString() ?? '—');
    }

    /**
     * Abonelik dışı bir işlem gelirse yalnızca müşteri bağını kaydeder;
     * hak vermez (tek seferlik ürünümüz yok).
     */
    private function recordCustomer(array $data): string
    {
        if (! empty($data['subscription_id'])) {
            return 'subscription transaction; handled by subscription events';
        }

        $user = $this->resolveUser($data);

        if ($user === null) {
            return 'no matching user';
        }

        $customerId = (string) ($data['customer_id'] ?? '');

        if ($customerId !== '' && $user->paddle_customer_id !== $customerId) {
            $user->forceFill(['paddle_customer_id' => $customerId])->save();
        }

        return 'customer linked; no entitlement (no one-off products)';
    }

    /**
     * Kullanıcıyı bul. SIRA ÖNEMLİ:
     *   1. `custom_data.user_id` — checkout açılırken bizim koyduğumuz bağ.
     *   2. `customer_id` — ilk bildirimde kaydedildi; yenilemelerde yeterli.
     *
     * E-postayla eşleştirme BİLEREK YOK: ödeyen e-posta hesabın e-postasıyla
     * aynı olmak zorunda değil ve e-posta değiştirilebilir bir alan.
     */
    private function resolveUser(array $data): ?User
    {
        $userId = $data['custom_data']['user_id'] ?? null;

        if (is_numeric($userId)) {
            $user = User::find((int) $userId);

            if ($user !== null) {
                return $user;
            }
        }

        $customerId = $data['customer_id'] ?? null;

        if (is_string($customerId) && $customerId !== '') {
            return User::firstWhere('paddle_customer_id', $customerId);
        }

        return null;
    }

    /** Abonelikteki kalemlerden bizim tanıdığımız ilk planı çıkarır. */
    private function planFor(array $data): ?Plan
    {
        foreach ($this->items($data) as $item) {
            $entry = $this->entryFor((string) ($item['price']['id'] ?? ''));
            $plan = Plan::tryFrom((string) ($entry['plan'] ?? ''));

            if ($plan !== null) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Dönem bitişi. Paddle vermiyorsa erişim uzatılmıyor: uydurma tarih,
     * bedava süre vermek ya da erişimi haksızca kesmek demek olurdu.
     */
    private function periodEnd(array $data): ?Carbon
    {
        $endsAt = $data['current_billing_period']['ends_at'] ?? null;

        if (! is_string($endsAt) || $endsAt === '') {
            return null;
        }

        try {
            return Carbon::parse($endsAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    private function items(array $data): array
    {
        return array_values(array_filter(
            (array) ($data['items'] ?? []),
            fn ($item) => is_array($item),
        ));
    }

    /**
     * Price ID → haritadaki karşılığı; yoksa boş dizi ve hiçbir hak yok.
     *
     * @return array<string, mixed>
     */
    private function entryFor(string $priceId): array
    {
        if ($priceId === '') {
            return [];
        }

        foreach ((array) config('paddle.prices') as $entry) {
            if (($entry['id'] ?? null) === $priceId) {
                return (array) $entry;
            }
        }

        return [];
    }
}
