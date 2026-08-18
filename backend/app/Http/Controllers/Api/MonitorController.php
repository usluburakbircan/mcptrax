<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $monitors = $request->user()->monitors()
            ->withCount(['alerts as open_alerts_count' => fn ($q) => $q->whereNull('resolved_at')])
            ->latest()
            ->get()
            ->map(fn (Monitor $monitor) => $this->payload($monitor));

        return response()->json(['success' => true, 'data' => ['monitors' => $monitors]]);
    }

    public function store(Request $request): JsonResponse
    {
        $plan = $request->user()->plan();

        if ($request->user()->monitors()->count() >= $plan->maxMonitors()) {
            return response()->json([
                'success' => false,
                'message' => "Your {$plan->label()} plan allows up to {$plan->maxMonitors()} monitor(s). Upgrade to add more.",
                'upgrade_required' => true,
            ], 422);
        }

        $data = $this->validated($request);

        $monitor = $request->user()->monitors()->create($data + [
            'slug' => Str::lower(Str::random(10)),
        ]);

        return response()->json(['success' => true, 'data' => ['monitor' => $this->payload($monitor)]], 201);
    }

    public function show(Request $request, Monitor $monitor): JsonResponse
    {
        $this->authorizeMonitor($request, $monitor);

        $recentChecks = $monitor->checks()->latest('started_at')->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'monitor' => $this->payload($monitor),
                'recent_checks' => $recentChecks,
            ],
        ]);
    }

    public function update(Request $request, Monitor $monitor): JsonResponse
    {
        $this->authorizeMonitor($request, $monitor);

        $monitor->update($this->validated($request));

        return response()->json(['success' => true, 'data' => ['monitor' => $this->payload($monitor->fresh())]]);
    }

    public function destroy(Request $request, Monitor $monitor): JsonResponse
    {
        $this->authorizeMonitor($request, $monitor);

        $monitor->delete();

        return response()->json(['success' => true]);
    }

    public function pause(Request $request, Monitor $monitor): JsonResponse
    {
        $this->authorizeMonitor($request, $monitor);

        $monitor->forceFill(['paused_at' => now()])->save();

        return response()->json(['success' => true, 'data' => ['monitor' => $this->payload($monitor)]]);
    }

    public function resume(Request $request, Monitor $monitor): JsonResponse
    {
        $this->authorizeMonitor($request, $monitor);

        $monitor->forceFill(['paused_at' => null, 'next_check_at' => now()])->save();

        return response()->json(['success' => true, 'data' => ['monitor' => $this->payload($monitor)]]);
    }

    protected function validated(Request $request): array
    {
        $plan = $request->user()->plan();

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:http,https', 'max:2048', function ($attribute, $value, $fail) {
                if (! app()->environment('local', 'testing') && ! \App\Probes\UrlGuard::isSafe($value)) {
                    $fail('Only publicly reachable MCP server URLs can be monitored.');
                }
            }],
            'interval_seconds' => [
                'sometimes', 'integer', 'max:86400',
                // Sınırın kaynağı Plan enum'u: Free 15 dk, Pro 1 dk.
                'min:'.$plan->minIntervalSeconds(),
            ],
            'auth_header_name' => ['nullable', 'string', 'max:128'],
            'auth_header_value' => ['nullable', 'string', 'max:2048'],
            'synthetic_tool_name' => [
                'nullable', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($plan) {
                    if ($value !== null && ! $plan->syntheticCalls()) {
                        $fail('Synthetic tool calls are a Pro feature.');
                    }
                },
            ],
            'synthetic_tool_args' => ['nullable', 'array'],
            'synthetic_expect_substring' => ['nullable', 'string', 'max:512'],
            'is_public' => ['sometimes', 'boolean'],
        ]);
    }

    protected function authorizeMonitor(Request $request, Monitor $monitor): void
    {
        abort_unless($monitor->user_id === $request->user()->id, 404);
    }

    protected function payload(Monitor $monitor): array
    {
        return [
            'id' => $monitor->id,
            'name' => $monitor->name,
            'url' => $monitor->url,
            'status' => $monitor->status,
            'interval_seconds' => $monitor->interval_seconds,
            'tools_count' => is_array($monitor->tool_names) ? count($monitor->tool_names) : null,
            'tool_names' => $monitor->tool_names,
            'has_auth' => $monitor->auth_header_value !== null,
            'synthetic_tool_name' => $monitor->synthetic_tool_name,
            'is_public' => $monitor->is_public,
            'slug' => $monitor->slug,
            'paused' => $monitor->isPaused(),
            'open_alerts_count' => $monitor->open_alerts_count ?? null,
            'last_checked_at' => $monitor->last_checked_at?->toIso8601String(),
            'last_status_change_at' => $monitor->last_status_change_at?->toIso8601String(),
        ];
    }
}
