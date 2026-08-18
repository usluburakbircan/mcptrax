<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlertChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertChannelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'channels' => $request->user()->alertChannels()->latest()->get()
                    ->map(fn (AlertChannel $channel) => $this->payload($channel)),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['email', 'slack', 'webhook'])],
            'target' => ['required', 'string', 'max:2048'],
        ]);

        if ($data['type'] !== 'email' && ! $request->user()->plan()->nonEmailChannels()) {
            return response()->json([
                'success' => false,
                'message' => 'Slack and webhook alerts are a Pro feature.',
                'upgrade_required' => true,
            ], 422);
        }

        $request->validate([
            'target' => $data['type'] === 'email' ? ['email'] : ['url:https', 'max:2048'],
        ]);

        $channel = $request->user()->alertChannels()->create($data);

        return response()->json(['success' => true, 'data' => ['channel' => $this->payload($channel)]], 201);
    }

    public function destroy(Request $request, AlertChannel $alertChannel): JsonResponse
    {
        abort_unless($alertChannel->user_id === $request->user()->id, 404);

        $alertChannel->delete();

        return response()->json(['success' => true]);
    }

    protected function payload(AlertChannel $channel): array
    {
        return [
            'id' => $channel->id,
            'type' => $channel->type,
            'target' => $channel->target,
            'is_active' => $channel->is_active,
        ];
    }
}
