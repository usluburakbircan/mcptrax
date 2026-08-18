<script setup lang="ts">
import type { MonitorStatus } from '~/types/api'

const props = defineProps<{
  status: MonitorStatus
  paused?: boolean
  /** Show the pulsing live dot on "up". */
  live?: boolean
}>()

const view = computed(() => {
  if (props.paused) {
    return { label: 'paused', dot: 'bg-mt-warn text-mt-warn', text: 'text-mt-warn', bg: 'bg-[var(--mt-warn-dim)]' }
  }
  switch (props.status) {
    case 'up':
      return { label: 'up', dot: 'bg-mt-up text-mt-up', text: 'text-mt-up', bg: 'bg-[var(--mt-up-dim)]' }
    case 'down':
      return { label: 'down', dot: 'bg-mt-down text-mt-down', text: 'text-mt-down', bg: 'bg-[var(--mt-down-dim)]' }
    default:
      return { label: 'pending', dot: 'bg-mt-faint text-mt-faint', text: 'text-mt-muted', bg: 'bg-mt-soft' }
  }
})
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 font-mono text-[11px] font-medium"
    :class="[view.text, view.bg]"
  >
    <span
      class="w-1.5 h-1.5 rounded-full shrink-0"
      :class="[view.dot, { 'mt-pulse': live && !paused && status === 'up' }]"
    />
    {{ view.label }}
  </span>
</template>
