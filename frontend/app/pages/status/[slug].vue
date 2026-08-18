<script setup lang="ts">
import type { ApiEnvelope, PublicStatus } from '~/types/api'

// Standalone shell: a status page is shared with people who have no account,
// so it carries no app navigation — only the monitor and a powered-by line.
definePageMeta({ layout: false })

const route = useRoute()
const { api } = useApi()

const slug = computed(() => String(route.params.slug))

const { data, pending, error } = await useAsyncData(
  () => `status-${slug.value}`,
  () => api<ApiEnvelope<PublicStatus>>(`/status/${slug.value}`),
)

const status = computed(() => data.value?.data ?? null)
const notFound = computed(() => {
  const err = error.value as any
  return (err?.response?.status ?? err?.statusCode) === 404
})

useHead({
  title: computed(() => status.value ? `${status.value.name} status — mcptrax` : 'Status — mcptrax'),
})

const verdict = computed(() => {
  switch (status.value?.status) {
    case 'up': return { label: 'Operational', cls: 'text-mt-up', dot: 'bg-mt-up text-mt-up' }
    case 'down': return { label: 'Down', cls: 'text-mt-down', dot: 'bg-mt-down text-mt-down' }
    default: return { label: 'Waiting for first check', cls: 'text-mt-muted', dot: 'bg-mt-faint text-mt-faint' }
  }
})

interface StripDay {
  key: string
  label: string
  cls: string
}

const localKey = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

// The API only returns days that have checks; the strip is always 90 bars,
// so missing dates are padded in as "no data" gray.
const strip = computed<StripDay[]>(() => {
  const byDate = new Map((status.value?.days ?? []).map(d => [d.date, d]))
  const out: StripDay[] = []
  const today = new Date()

  for (let i = 89; i >= 0; i--) {
    const d = new Date(today)
    d.setDate(today.getDate() - i)
    const key = localKey(d)
    const day = byDate.get(key)

    if (!day || day.checks === 0) {
      out.push({ key, label: `${key} — no data`, cls: 'bg-mt-soft' })
    } else if (day.failures === 0) {
      out.push({ key, label: `${key} — ${day.checks} checks, all passed`, cls: 'bg-mt-up' })
    } else if (day.failures / day.checks > 0.5) {
      out.push({ key, label: `${key} — ${day.failures} of ${day.checks} checks failed`, cls: 'bg-mt-down' })
    } else {
      out.push({ key, label: `${key} — ${day.failures} of ${day.checks} checks failed`, cls: 'bg-mt-warn' })
    }
  }
  return out
})

const uptimeLabel = computed(() =>
  status.value?.uptime_90d != null ? `${status.value.uptime_90d.toFixed(2)}%` : '—')
</script>

<template>
  <div class="min-h-full flex flex-col bg-mt-bg">
    <main class="flex-1 w-full max-w-2xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
      <div v-if="pending && !status" class="mt-card h-48 animate-pulse" />

      <div v-else-if="notFound" class="text-center py-16">
        <p class="font-mono text-[13px] text-mt-faint mb-4">404</p>
        <h1 class="text-2xl font-semibold mb-2">This status page does not exist</h1>
        <p class="text-[14px] text-mt-muted max-w-sm mx-auto">
          The link may be wrong, or the owner has made this monitor private.
        </p>
      </div>

      <div v-else-if="error && !status" class="text-center py-16">
        <h1 class="text-2xl font-semibold mb-2">Status unavailable</h1>
        <p class="text-[14px] text-mt-muted max-w-sm mx-auto">
          This status page could not be loaded right now. Refresh to try again.
        </p>
      </div>

      <template v-else-if="status">
        <p class="mt-eyebrow mb-3">Service status</p>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
          <h1 class="text-2xl sm:text-3xl font-semibold">{{ status.name }}</h1>
          <span class="inline-flex items-center gap-2.5">
            <span
              class="relative w-2.5 h-2.5 rounded-full shrink-0"
              :class="[verdict.dot, { 'mt-pulse': status.status === 'up' }]"
            />
            <span class="font-mono text-[14px] font-semibold" :class="verdict.cls">{{ verdict.label }}</span>
          </span>
        </div>
        <p class="font-mono text-[12px] text-mt-faint mb-10">
          {{ status.last_checked_at ? `last checked ${formatTimeAgo(status.last_checked_at)}` : 'not checked yet' }}
        </p>

        <!-- Numbers -->
        <div class="grid grid-cols-3 gap-3 mb-10">
          <div class="mt-card px-4 py-3.5">
            <p class="font-mono text-[10.5px] uppercase tracking-wider text-mt-faint mb-1">Uptime · 90d</p>
            <p class="font-mono text-[19px] font-semibold" :class="status.uptime_90d != null ? 'text-mt-up' : 'text-mt-muted'">{{ uptimeLabel }}</p>
          </div>
          <div class="mt-card px-4 py-3.5">
            <p class="font-mono text-[10.5px] uppercase tracking-wider text-mt-faint mb-1">Latency p50</p>
            <p class="font-mono text-[19px] font-semibold text-mt-text">{{ status.latency.p50_ms != null ? `${status.latency.p50_ms}ms` : '—' }}</p>
          </div>
          <div class="mt-card px-4 py-3.5">
            <p class="font-mono text-[10.5px] uppercase tracking-wider text-mt-faint mb-1">Latency p95</p>
            <p class="font-mono text-[19px] font-semibold text-mt-text">{{ status.latency.p95_ms != null ? `${status.latency.p95_ms}ms` : '—' }}</p>
          </div>
        </div>

        <!-- 90-day strip -->
        <div class="mt-card p-4 sm:p-5">
          <div class="flex items-center justify-between mb-3">
            <p class="font-mono text-[10.5px] uppercase tracking-wider text-mt-faint">Last 90 days</p>
          </div>
          <div class="grid gap-px" style="grid-template-columns: repeat(90, minmax(0, 1fr));">
            <span
              v-for="day in strip" :key="day.key"
              class="h-9 rounded-[1px]"
              :class="day.cls"
              :title="day.label"
            />
          </div>
          <div class="flex justify-between mt-2 font-mono text-[10.5px] text-mt-faint">
            <span>90 days ago</span>
            <span>today</span>
          </div>
        </div>
      </template>
    </main>

    <footer class="border-t border-mt-border-soft">
      <div class="max-w-2xl mx-auto px-4 sm:px-6 py-6 text-center">
        <NuxtLink to="/" class="inline-flex items-center gap-2 font-mono text-[12px] text-mt-faint hover:text-mt-muted transition">
          Powered by <span class="text-mt-text">mcptrax</span>
        </NuxtLink>
      </div>
    </footer>
  </div>
</template>
