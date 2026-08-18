<script setup lang="ts">
import type { PublicCheckReport } from '~/types/api'

const props = defineProps<{ report: PublicCheckReport }>()

const PHASE_ORDER = ['connect', 'handshake', 'tools_list'] as const

type PhaseState = 'ok' | 'fail' | 'skipped'

interface PhaseView {
  key: string
  label: string
  ms: number | null
  state: PhaseState
  detail: string | null
}

const failedIndex = computed(() => {
  if (props.report.ok || !props.report.failed_phase) return -1
  const i = PHASE_ORDER.indexOf(props.report.failed_phase as typeof PHASE_ORDER[number])
  // An unknown phase name still means failure — pin it to the first phase
  // rather than rendering everything green.
  return i === -1 ? 0 : i
})

const stateAt = (index: number): PhaseState => {
  if (failedIndex.value === -1) return 'ok'
  if (index < failedIndex.value) return 'ok'
  if (index === failedIndex.value) return 'fail'
  return 'skipped'
}

const phases = computed<PhaseView[]>(() => {
  const r = props.report
  const serverDetail = r.server_name
    ? `${r.server_name}${r.server_version ? ` v${r.server_version}` : ''}${r.protocol_version ? ` · ${r.protocol_version}` : ''}`
    : null

  return [
    { key: 'connect', label: 'Connection', ms: r.connect_ms, state: stateAt(0), detail: null },
    { key: 'handshake', label: 'Handshake', ms: null, state: stateAt(1), detail: serverDetail },
    {
      key: 'tools_list',
      label: 'Tools list',
      ms: r.tools_list_ms,
      state: stateAt(2),
      detail: r.tools?.length ? `${r.tools.length} tool${r.tools.length === 1 ? '' : 's'}` : null,
    },
  ]
})

const mark = (state: PhaseState) => (state === 'ok' ? '✓' : state === 'fail' ? '✕' : '—')
const markClass = (state: PhaseState) =>
  state === 'ok' ? 'text-mt-up border-mt-up/40 bg-[var(--mt-up-dim)]'
  : state === 'fail' ? 'text-mt-down border-mt-down/40 bg-[var(--mt-down-dim)]'
  : 'text-mt-faint border-mt-border bg-mt-soft'
</script>

<template>
  <div class="mt-card overflow-hidden">
    <!-- Verdict bar -->
    <div
      class="flex flex-wrap items-center justify-between gap-2 px-4 sm:px-5 py-3 border-b border-mt-border-soft"
      :class="report.ok ? 'bg-[var(--mt-up-dim)]' : 'bg-[var(--mt-down-dim)]'"
    >
      <span class="font-mono text-[13px] font-semibold" :class="report.ok ? 'text-mt-up' : 'text-mt-down'">
        {{ report.ok ? 'SERVER HEALTHY' : 'CHECK FAILED' }}
      </span>
      <span v-if="report.total_ms != null" class="font-mono text-[12px] text-mt-muted">
        total {{ report.total_ms }}ms
      </span>
    </div>

    <!-- Phase trace -->
    <ol class="px-4 sm:px-5 py-4">
      <li
        v-for="(phase, i) in phases" :key="phase.key"
        class="relative flex items-start gap-3 pb-4 last:pb-0"
      >
        <!-- connector line -->
        <span
          v-if="i < phases.length - 1"
          class="absolute left-[11px] top-6 bottom-0 w-px"
          :class="phase.state === 'ok' ? 'bg-mt-up/30' : 'bg-mt-border'"
          aria-hidden="true"
        />
        <span
          class="grid place-items-center w-[23px] h-[23px] rounded-full border font-mono text-[11px] shrink-0 mt-px"
          :class="markClass(phase.state)"
        >{{ mark(phase.state) }}</span>

        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
            <span class="text-[14px] font-medium" :class="phase.state === 'skipped' ? 'text-mt-faint' : 'text-mt-text'">
              {{ phase.label }}
            </span>
            <span v-if="phase.ms != null && phase.state !== 'skipped'" class="font-mono text-[12px] text-mt-muted">
              {{ phase.ms }}ms
            </span>
            <span v-if="phase.state === 'skipped'" class="font-mono text-[11px] text-mt-faint">skipped</span>
          </div>

          <p v-if="phase.detail && phase.state === 'ok'" class="mt-0.5 font-mono text-[12px] text-mt-faint truncate">
            {{ phase.detail }}
          </p>

          <!-- failure detail -->
          <div v-if="phase.state === 'fail'" class="mt-1.5 rounded-md border border-mt-down/25 bg-[var(--mt-down-dim)] px-3 py-2">
            <p v-if="report.error_class" class="font-mono text-[11px] text-mt-down/80 mb-0.5">{{ report.error_class }}</p>
            <p class="text-[13px] text-mt-text break-words">{{ report.error_message || 'The server did not respond as expected at this phase.' }}</p>
          </div>

          <!-- tool list on success -->
          <div v-if="phase.key === 'tools_list' && phase.state === 'ok' && report.tools?.length" class="mt-2 flex flex-wrap gap-1.5">
            <span v-for="tool in report.tools" :key="tool.name" class="mt-chip" :title="tool.description ?? undefined">
              {{ tool.name }}
            </span>
          </div>
        </div>
      </li>
    </ol>
  </div>
</template>
