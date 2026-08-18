<script setup lang="ts">
import type { ApiEnvelope, Monitor } from '~/types/api'

definePageMeta({ layout: 'app', middleware: 'auth' })
useHead({ title: 'Monitors — mcptrax' })

const { api } = useApi()
const auth = useAuthStore()
const { openCheckout, opening } = usePaddle()

const { data, pending, error, refresh } = await useAsyncData(
  'monitors',
  () => api<ApiEnvelope<{ monitors: Monitor[] }>>('/monitors'),
)

const monitors = computed(() => data.value?.data.monitors ?? [])

const intervalLabel = (seconds: number) => {
  if (seconds % 3600 === 0) return `${seconds / 3600}h`
  if (seconds % 60 === 0) return `${seconds / 60}m`
  return `${seconds}s`
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <div>
        <div class="flex items-center gap-3 flex-wrap">
          <h1 class="text-xl font-semibold">Monitors</h1>
          <button
            v-if="auth.user?.plan === 'free'"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-full border border-mt-up/30 px-2.5 py-1 font-mono text-[11px] font-medium text-mt-up transition hover:bg-[var(--mt-up-dim)]"
            :disabled="opening"
            @click="openCheckout('monthly')"
          >
            Free plan · Upgrade to Pro
          </button>
        </div>
        <p class="text-[13px] text-mt-faint mt-0.5">Every server, checked on schedule.</p>
      </div>
      <NuxtLink to="/app/monitors/new" class="mt-btn-primary">
        <span class="font-mono">+</span> Add monitor
      </NuxtLink>
    </div>

    <div v-if="pending" class="space-y-2" aria-label="Loading monitors">
      <div v-for="i in 3" :key="i" class="mt-card h-[76px] animate-pulse" />
    </div>

    <div v-else-if="error" class="mt-card p-8 text-center">
      <p class="text-[14px] text-mt-muted mb-4">Could not load your monitors.</p>
      <button class="mt-btn-ghost" @click="refresh()">Try again</button>
    </div>

    <div v-else-if="!monitors.length" class="mt-card px-6 py-14 text-center">
      <svg viewBox="0 0 32 32" class="w-9 h-9 mx-auto mb-4 opacity-60" aria-hidden="true">
        <path d="M4 22 L12 22 L16 9 L20 22 L28 22" fill="none" stroke="var(--mt-faint)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
      </svg>
      <h2 class="text-[16px] font-semibold mb-1.5">No monitors yet</h2>
      <p class="text-[13.5px] text-mt-muted mb-6 max-w-sm mx-auto">
        Add your first MCP server and mcptrax starts checking it right away.
      </p>
      <NuxtLink to="/app/monitors/new" class="mt-btn-primary">Add your first monitor</NuxtLink>
    </div>

    <ul v-else class="space-y-2">
      <li v-for="m in monitors" :key="m.id">
        <NuxtLink
          :to="`/app/monitors/${m.id}`"
          class="mt-card flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 px-4 sm:px-5 py-4 transition hover:border-mt-border hover:bg-mt-soft/60"
        >
          <div class="flex items-center gap-3 min-w-0 flex-1">
            <StatusBadge :status="m.status" :paused="m.paused" live />
            <div class="min-w-0">
              <p class="text-[14.5px] font-medium text-mt-text truncate">{{ m.name }}</p>
              <p class="font-mono text-[12px] text-mt-faint truncate">{{ m.url }}</p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-x-5 gap-y-1 font-mono text-[12px] text-mt-muted sm:justify-end shrink-0">
            <span v-if="m.open_alerts_count > 0" class="inline-flex items-center gap-1.5 text-mt-down">
              <span class="w-1.5 h-1.5 rounded-full bg-mt-down" />
              {{ m.open_alerts_count }} open alert{{ m.open_alerts_count === 1 ? '' : 's' }}
            </span>
            <span v-if="m.tools_count != null">{{ m.tools_count }} tools</span>
            <span>every {{ intervalLabel(m.interval_seconds) }}</span>
            <span v-if="m.last_checked_at" class="text-mt-faint">{{ formatTimeAgo(m.last_checked_at) }}</span>
            <span v-else class="text-mt-faint">not checked yet</span>
          </div>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
