<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Paddle\PaddlePortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    /**
     * Frontend'in checkout açması için gereken herkese-açık yapılandırma.
     * `client_token` frontend'e verilmesi güvenli olan anahtar; API key ASLA
     * buradan dönmez.
     */
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'environment' => config('paddle.env'),
                'client_token' => config('paddle.client_token'),
                'prices' => [
                    'pro_monthly' => config('paddle.prices.pro_monthly.id'),
                    'pro_yearly' => config('paddle.prices.pro_yearly.id'),
                ],
                // Checkout'a custom_data.user_id olarak gömülür; webhook
                // kullanıcıyı bu bağla bulur.
                'user_id' => $request->user()->id,
            ],
        ]);
    }

    /**
     * Müşteri portalına kısa ömürlü giriş linki. Müşteri kimliği İSTEKTEN
     * ALINMIYOR — yalnızca oturumdaki kullanıcının kendi kaydından okunuyor;
     * aksi halde herhangi bir oturum sahibi başkasının fatura ekranına link
     * isteyebilirdi.
     */
    public function portal(Request $request, PaddlePortal $portal): JsonResponse
    {
        $user = $request->user();

        if (! $user->paddle_customer_id) {
            return response()->json([
                'success' => false,
                'message' => 'No billing account yet. The portal opens after your first payment.',
            ], 409);
        }

        try {
            $urls = $portal->createSession($user->paddle_customer_id, $user->paddle_subscription_id);
        } catch (\Throwable $e) {
            Log::error('Paddle portal unavailable', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Billing portal is temporarily unavailable. Please try again shortly.',
            ], 502);
        }

        return response()->json(['success' => true, 'data' => $urls]);
    }
}
