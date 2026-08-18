<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected const MAX_ACTIVE_SESSIONS = 5;

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return $this->successResponse([
            'user' => $this->userPayload($user),
            'token' => $this->issueToken($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        return $this->successResponse([
            'user' => $this->userPayload($user),
            'token' => $this->issueToken($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Süresi geçen token'lar temizlenir ve eşzamanlı oturum sayısı sınırlanır;
     * aksi halde süresiz token'lar hesap başına sınırsız birikir.
     */
    protected function issueToken(User $user): string
    {
        $expiration = config('sanctum.expiration');

        if ($expiration) {
            $user->tokens()->where('created_at', '<', now()->subMinutes((int) $expiration))->delete();
        }

        $keep = $user->tokens()->latest('id')->limit(self::MAX_ACTIVE_SESSIONS - 1)->pluck('id');

        if ($keep->isNotEmpty()) {
            $user->tokens()->whereNotIn('id', $keep)->delete();
        }

        return $user->createToken('auth')->plainTextToken;
    }

    protected function userPayload(User $user): array
    {
        $plan = $user->plan();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'monitors_count' => $user->monitors()->count(),
            // GEÇERLİ plan (süresi dolmuşsa free) — ham kolon değil.
            'plan' => $plan->value,
            'plan_label' => $plan->label(),
            'pro_until' => $user->pro_until?->toIso8601String(),
            // Portal yalnızca kapısı olan kullanıcıya gösterilir; ham
            // paddle_customer_id bilerek dışarı verilmiyor.
            'has_billing_portal' => $user->paddle_customer_id !== null,
            'limits' => [
                'max_monitors' => $plan->maxMonitors(),
                'min_interval_seconds' => $plan->minIntervalSeconds(),
                'synthetic_calls' => $plan->syntheticCalls(),
                'non_email_channels' => $plan->nonEmailChannels(),
                'retention_days' => $plan->retentionDays(),
            ],
        ];
    }

    protected function successResponse(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
