<x-mail::message>
@if ($alert->kind === 'drift')
# ⚠️ Tools changed on {{ $monitor->name }}

The tool inventory of your MCP server changed:

**{{ $alert->error_message }}**
@elseif ($opened)
# 🔴 {{ $monitor->name }} is down

Your MCP server failed its health check at the **{{ $alert->reason ?? 'connection' }}** phase.

@if ($alert->error_message)
```
{{ $alert->error_message }}
```
@endif
@else
# 🟢 {{ $monitor->name }} recovered

Your MCP server is responding normally again.

Downtime: {{ $alert->opened_at->diffForHumans($alert->resolved_at ?? now(), true) }}.
@endif

**Server:** `{{ $monitor->url }}`

<x-mail::button :url="config('app.frontend_url', config('app.url')).'/app/monitors/'.$monitor->id">
View monitor
</x-mail::button>

Thanks,<br>
mcptrax
</x-mail::message>
