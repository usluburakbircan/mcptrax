<script setup lang="ts">
import type { ApiEnvelope, CheckRow, Monitor } from '~/types/api'

definePageMeta({ layout: 'app', middleware: 'auth' })

const route = useRoute()
const { api } = useApi()
const toast = useToast()

const id = computed(() => String(route.params.id))

const { data, pending, error, refresh } = await useAsyncData(
  () => `monitor-${id.value}`,
  () => api<ApiEnvelope<{ monitor: Monitor, recent_checks: CheckRow[] }>>(`/monitors/${id.value}`),
)

const monitor = computed(() => data.value?.data.monitor ?? null)
const checks = computed(() => data.value?.data.recent_checks ?? [])

useHead({ title: computed(() => monitor.value ? `${monitor.value.name} — mcptrax` : 'Monitor — mcptrax') })

const acting = ref(false)
const expanded = ref<number | null>(null)

const togglePause = async () => {
  if (!monitor.value || acting.value) return
  acting.value = true
  const action = monitor.value.paused ? 'resume' : 'pause'
  try {
    await api(`/monitors/${id.value}/${action}`, { method: 'POST' })
    toast.success(action === 'pause' ? 'Monitor paused. Checks are on hold.' : 'Monitor resumed. Checks are running again.')
    await refresh()
  } catch (err: any) {
    toast.error(apiErrorMessage(err, `Could not ${action} the monitor.`))
  } finally {
    acting.value = false
  }
}

const onDelete = async () => {
  if (!monitor.value || acting.value) return
  if (!confirm(`Delete "${monitor.value.name}"? Its check history goes with it.`)) return
  acting.value = true
  try {
    await api(`/monitors/${id.value}`, { method: 'DELETE' })
    toast.success('Monitor deleted.')
    await navigateTo('/app')
  } catch (err: any) {
    toast.error(apiErrorMessage(err, 'Could not delete the monitor.'))
    acting.value = false
  }
}

const intervalLabel = (seconds: number) => {
  if (seconds % 3600 === 0) return `${seconds / 3600}h`
  if (seconds % 60 === 0) return `${seconds / 60}m`
  return `${seconds}s`
}

const phaseLabel = (phase: string | null) => {
  switch (phase) {
    case 'connect': return 'connection'
    case 'handshake': return 'handshake'
    case 'tools_list': return 'tools list'
    case 'tool_call': return 'tool call'
    default: return phase ?? 'unknown'
  }
}

const formatTime = (iso: string) => {
  const d = new Date(iso)
  return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
}
</script>

<template>
  <div>
    <NuxtLink to="/app" class="font-mono text-[12px] text-mt-faint hover:text-mt-muted transition">← Monitors</NuxtLink>

    <div v-if="pending && !monitor" class="mt-card h-32 animate-pulse mt-4" />

    <div v-else-if="error" class="mt-card p-8 text-center mt-4">
      <p class="text-[14px] text-mt-muted mb-4">This monitor could not be loaded. It may have been deleted.</p>
      <NuxtLink to="/app" class="mt-btn-ghost">Back to monitors</NuxtLink>
    </div>

    <template v-else-if="monitor">
      <!-- Header -->
      <div class="flex flex-wrap items-start justify-between gap-4 mt-3 mb-8">
        <div class="min-w-0">
          <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-semibold">{{ monitor.name }}</h1>
            <StatusBadge :status="monitor.status" :paused="monitor.paused" live />
            <span v-if="monitor.open_alerts_count > 0" class="font-mono text-[11.5px] text-mt-down">
              {{ monitor.open_alerts_count }} open alert{{ monitor.open_alerts_count === 1 ? '' : 's' }}
            </span>
          </div>
          <p class="font-mono text-[12.5px] text-mt-faint mt-1.5 break-all">{{ monitor.url }}</p>
          <p class="font-mono text-[12px] text-mt-faint mt-1">
            every {{ intervalLabel(monitor.interval_seconds) }}
            <span v-if="monitor.last_checked_at"> · last check {{ formatTimeAgo(monitor.last_checked_at) }}</span>
            <span v-if="monitor.last_status_change_at"> · {{ monitor.status }} since {{ formatTimeAgo(monitor.last_status_change_at) }}</span>
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button class="mt-btn-ghost" :disabled="acting" @click="togglePause">
            {{ monitor.paused ? 'Resume' : 'Pause' }}
          </button>
          <NuxtLink :to="`/app/monitors/${monitor.id}/edit`" class="mt-btn-ghost">Edit</NuxtLink>
          <button class="mt-btn-danger" :disabled="acting" @click="onDelete">Delete</button>
        </div>
      </div>

      <!-- Tools -->
      <section v-if="monitor.tool_names?.length" class="mb-8">
        <p class="mt-eyebrow mb-3">Tools · {{ monitor.tool_names.length }}</p>
        <div class="flex flex-wrap gap-1.5">
          <span v-for="tool in monitor.tool_names" :key="tool" class="mt-chip">{{ tool }}</span>
        </div>
      </section>

      <!-- Recent checks -->
      <section>
        <p class="mt-eyebrow mb-3">Recent checks</p>

        <div v-if="!checks.length" class="mt-card px-6 py-10 text-center">
          <p class="text-[13.5px] text-mt-muted">No checks yet. The first one runs within the check interval.</p>
        </div>

        <div v-else class="mt-card overflow-x-auto">
          <table class="w-full text-left min-w-[720px]">
            <thead>
              <tr class="border-b border-mt-border-soft">
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint">Time</th>
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint">Result</th>
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint text-right">Connect</th>
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint text-right">Tools list</th>
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint text-right">Tool call</th>
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint text-right">Total</th>
                <th class="px-4 py-2.5 font-mono text-[11px] font-medium uppercase tracking-wider text-mt-faint text-right">Tools</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="check in checks" :key="check.id">
                <tr
                  class="border-b border-mt-border-soft last:border-b-0 transition"
                  :class="{ 'cursor-pointer hover:bg-mt-soft/60': !check.ok, 'bg-mt-soft/40': expanded === check.id }"
                  @click="!check.ok && (expanded = expanded === check.id ? null : check.id)"
                >
                  <td class="px-4 py-2.5 font-mono text-[12px] text-mt-muted whitespace-nowrap">{{ formatTime(check.started_at) }}</td>
                  <td class="px-4 py-2.5">
                    <span
                      class="inline-flex items-center gap-1.5 font-mono text-[11.5px] font-medium"
                      :class="check.ok ? 'text-mt-up' : 'text-mt-down'"
                    >
                      {{ check.ok ? '✓ ok' : `✕ ${phaseLabel(check.failed_phase)}` }}
                    </span>
                    <span
                      v-if="check.tools_drift"
                      class="ml-2 inline-flex items-center rounded bg-[var(--mt-warn-dim)] px-1.5 py-0.5 font-mono text-[10.5px] font-medium text-mt-warn"
                      title="The tool list changed compared to the previous check"
                    >drift</span>
                  </td>
                  <td class="px-4 py-2.5 font-mono text-[12px] text-mt-muted text-right">{{ check.connect_ms != null ? `${check.connect_ms}ms` : '—' }}</td>
                  <td class="px-4 py-2.5 font-mono text-[12px] text-mt-muted text-right">{{ check.tools_list_ms != null ? `${check.tools_list_ms}ms` : '—' }}</td>
                  <td class="px-4 py-2.5 font-mono text-[12px] text-mt-muted text-right">{{ check.tool_call_ms != null ? `${check.tool_call_ms}ms` : '—' }}</td>
                  <td class="px-4 py-2.5 font-mono text-[12px] text-right" :class="check.ok ? 'text-mt-text' : 'text-mt-muted'">
                    {{ check.latency_ms != null ? `${check.latency_ms}ms` : '—' }}
                  </td>
                  <td class="px-4 py-2.5 font-mono text-[12px] text-mt-muted text-right">{{ check.tools_count ?? '—' }}</td>
                </tr>
                <tr v-if="expanded === check.id && !check.ok" class="border-b border-mt-border-soft last:border-b-0">
                  <td colspan="7" class="px-4 pb-3 pt-0 bg-mt-soft/40">
                    <div class="rounded-md border border-mt-down/25 bg-[var(--mt-down-dim)] px-3.5 py-2.5">
                      <p v-if="check.error_class" class="font-mono text-[11px] text-mt-down/80 mb-1">{{ check.error_class }}</p>
                      <p class="text-[13px] text-mt-text break-words">{{ check.error_message || 'No error message was recorded for this check.' }}</p>
                      <p v-if="check.server_name" class="mt-1.5 font-mono text-[11px] text-mt-faint">
                        {{ check.server_name }}{{ check.server_version ? ` v${check.server_version}` : '' }}{{ check.protocol_version ? ` · ${check.protocol_version}` : '' }}
                      </p>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <p v-if="checks.some(c => !c.ok)" class="mt-2 text-[12px] text-mt-faint">Select a failed check to see its error.</p>
      </section>
    </template>
  </div>
</template>
