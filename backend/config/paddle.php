<?php

return [

    /**
     * Sandbox mı live mı? Yalnızca etiket değil — API adresini, webhook
     * imzasını ve price ID'lerin hangi dünyaya ait olduğunu belirler.
     * İki ortam TAMAMEN ayrı: aynı ürünün iki ortamdaki ID'si farklı.
     */
    'env' => env('PADDLE_ENV', 'sandbox'),

    'api_base' => env('PADDLE_ENV', 'sandbox') === 'live'
        ? 'https://api.paddle.com'
        : 'https://sandbox-api.paddle.com',

    /**
     * Sunucu tarafı API anahtarı. GİZLİ — frontend'e asla verilmez;
     * orada `client_token` var ve o başka bir şey.
     */
    'api_key' => env('PADDLE_API_KEY'),

    /**
     * Paddle.js'in checkout açmak için kullandığı istemci anahtarı.
     * Frontend'e verilmesi güvenli; yalnızca checkout başlatabilir.
     */
    'client_token' => env('PADDLE_CLIENT_TOKEN'),

    /**
     * Webhook imza anahtarı (`ntfset_...` bildirim ayarında üretilir).
     * Boşsa webhook hiçbir şey açmamalı: imzasız gövdeye güvenmek
     * "isteyene abonelik" demek olurdu.
     */
    'webhook_secret' => env('PADDLE_WEBHOOK_SECRET'),

    /**
     * Fiyat haritası: Paddle price ID → hangi planı açtığı.
     *
     * Webhook'un getirdiği price ID BURADA yoksa hiçbir hak verilmez.
     * "Ödendi" bilgisi tek başına yeterli değil — hangi ürünün ödendiğini
     * biz bilmek zorundayız. Varsayılanlar sandbox ID'leri (18 Ağu 2026).
     */
    'prices' => [
        'pro_monthly' => [
            'id' => env('PADDLE_PRICE_PRO_MONTHLY', 'pri_01m0a9329n0dchkrcvgdjkyha0'),
            'plan' => 'pro',
        ],
        'pro_yearly' => [
            'id' => env('PADDLE_PRICE_PRO_YEARLY', 'pri_01m0a976tgqcdyxew6qfhc32pd'),
            'plan' => 'pro',
        ],
    ],

    /** Panelde iz sürmek için; kodda kullanılmıyor. */
    'sandbox_products' => [
        'pro' => 'pro_01m0a91a6xpnz79pdhmgd49n56',
    ],

];
