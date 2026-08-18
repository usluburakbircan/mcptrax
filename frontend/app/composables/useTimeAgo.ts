import type { MaybeRefOrGetter } from 'vue'

// One module-level interval refreshes every consumer once a minute. The
// reference counter shuts the interval down when the last consumer leaves.
const tick = ref(0)
let interval: ReturnType<typeof setInterval> | null = null
let consumers = 0

function acquireTicker() {
  if (!import.meta.client) return

  consumers++
  if (!interval) {
    interval = setInterval(() => { tick.value++ }, 60_000)
  }
}

function releaseTicker() {
  if (!import.meta.client) return

  consumers = Math.max(0, consumers - 1)
  if (consumers === 0 && interval) {
    clearInterval(interval)
    interval = null
  }
}

export function formatTimeAgo(raw: string | Date): string {
  const d = raw instanceof Date ? raw : new Date(raw)
  if (Number.isNaN(d.getTime())) return ''

  const seconds = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000))
  if (seconds < 45) return 'just now'
  if (seconds < 90) return '1 min ago'
  const minutes = Math.round(seconds / 60)
  if (minutes < 60) return `${minutes} min ago`
  const hours = Math.round(minutes / 60)
  if (hours < 2) return '1 hour ago'
  if (hours < 24) return `${hours} hours ago`
  const days = Math.round(hours / 24)
  if (days === 1) return 'yesterday'
  if (days < 7) return `${days} days ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

export function useTimeAgo(input: MaybeRefOrGetter<string | Date | null | undefined>) {
  acquireTicker()

  if (getCurrentInstance()) {
    onScopeDispose(releaseTicker)
  }

  return computed(() => {
    // tick.value is read so the computed re-evaluates once a minute.
    void tick.value

    const raw = toValue(input)
    if (!raw) return ''

    return formatTimeAgo(raw)
  })
}
