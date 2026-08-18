<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = Alert::query()
            ->whereIn('monitor_id', $request->user()->monitors()->select('id'))
            ->with('monitor:id,name')
            ->latest('opened_at')
            ->limit(100)
            ->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'monitor_id' => $alert->monitor_id,
                'monitor_name' => $alert->monitor->name,
                'kind' => $alert->kind,
                'opened_at' => $alert->opened_at->toIso8601String(),
                'resolved_at' => $alert->resolved_at?->toIso8601String(),
                'reason' => $alert->reason,
                'error_message' => $alert->error_message,
            ]);

        return response()->json(['success' => true, 'data' => ['alerts' => $alerts]]);
    }
}
