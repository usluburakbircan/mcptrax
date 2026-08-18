<script setup lang="ts">
import type { Alert, AlertChannel, ApiEnvelope, BillingPortal, ChannelType } from '~/types/api'

definePageMeta({ layout: 'app', middleware: 'auth' })
useHead({ title: 'Alerts & settings — mcptrax' })

const { api } = useApi()
const toast = useToast()
const auth = useAuthStore()
const route = useRoute()
const { openCheckout } = usePaddle()

// ---- Billing ----

const user = computed(() => auth.user)
const isFree = computed(() => user.value?.plan !== 'pro')

const limitItems = computed(() => {
  const limits = user.value?.limits
  if (!limits) return []
  const interval = limits.min_interval_seconds % 60 === 0
    ? `${limits.min_interval_seconds / 60}-minute`
    : `${limits.min_interval_seconds}-second`
  return [
    limits.max_monitors == null ? 'Unlimited monitored servers' : `${limits.max_monitors} monitored server${limits.max_monitors === 1 ? '' : 's'}`,
    `Checks down to every ${interval.replace('1-minute', 'minute')}`,
    limits.synthetic_calls ? 'Synthetic tool calls included' : 'No synthetic tool calls',
    limits.non_email_channels ? 'Email, Slack & webhook alerts' : 'Email alerts only',
    `${limits.retention_days}-day check history`,
  ]
})

const proUntilLabel = computed(() => {
  if (!user.value?.pro_until) return ''
  const d = new Date(user.value.pro_until)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
})

const portalLoading = ref(false)
const portalError = ref('')
const portalUrl = ref('')

const openPortal = async () => {
  if (portalLoading.value) return
  portalLoading.value = true
  portalError.value = ''
  portalUrl.value = ''

  // Opened synchronously with the click so popup blockers allow it; the
  // URL is filled in once Paddle answers.
  const win = window.open('about:blank', '_blank')

  try {
    const res = await api<ApiEnvelope<BillingPortal>>('/billing/portal', { method: 'POST' })
    if (win) {
      win.location.href = res.data.overview
    } else {
      // Popup blocked: hand the user a link instead.
      portalUrl.value = res.data.overview
    }
  } catch (err: any) {
    win?.close()
    portalError.value = apiErrorMessage(err, 'Could not open the billing portal. Try again in a moment.')
  } finally {
    portalLoading.value = false
  }
}

// Registration with ?plan=pro lands here: open the checkout they asked for.
onMounted(() => {
  if (route.query.upgrade === 'pro' && isFree.value) {
    void openCheckout('monthly')
  }
})

const { data: channelData, refresh: refreshChannels } = await useAsyncData(
  'alert-channels',
  () => api<ApiEnvelope<{ channels: AlertChannel[] }>>('/alert-channels'),
)
const { data: alertData, refresh: refreshAlerts } = await useAsyncData(
  'alerts',
  () => api<ApiEnvelope<{ alerts: Alert[] }>>('/alerts'),
)

const channels = computed(() => channelData.value?.data.channels ?? [])
const alerts = computed(() => alertData.value?.data.alerts ?? [])

const TYPE_META: Record<ChannelType, { label: string, placeholder: string, hint: string }> = {
  email: { label: 'Email', placeholder: 'you@company.com', hint: 'Alerts arrive as plain emails.' },
  slack: { label: 'Slack', placeholder: 'https://hooks.slack.com/services/T000/B000/XXXX', hint: 'An incoming webhook URL for your channel. Pro plan.' },
  webhook: { label: 'Webhook', placeholder: 'https://api.your-app.com/mcptrax-alerts', hint: 'mcptrax POSTs a JSON payload on open and resolve. Pro plan.' },
}

const newType = ref<ChannelType>('email')
const newTarget = ref('')
const adding = ref(false)
const targetError = ref('')
const channelUpgradeMessage = ref('')

const addChannel = async () => {
  if (adding.value) return
  adding.value = true
  targetError.value = ''
  channelUpgradeMessage.value = ''
  try {
    await api('/alert-channels', {
      method: 'POST',
      body: { type: newType.value, target: newTarget.value.trim() },
    })
    toast.success('Alert channel added.')
    newTarget.value = ''
    await refreshChannels()
  } catch (err: any) {
    if (isUpgradeRequired(err)) {
      channelUpgradeMessage.value = apiErrorMessage(err, 'Slack and webhook alerts need the Pro plan.')
      return
    }
    const fields = apiFieldErrors(err)
    targetError.value = fields.target?.[0] ?? fields.type?.[0] ?? ''
    if (!targetError.value) toast.error(apiErrorMessage(err, 'Could not add the channel.'))
  } finally {
    adding.value = false
  }
}

const removeChannel = async (channel: AlertChannel) => {
  if (!confirm(`Remove the ${channel.type} channel "${channel.target}"?`)) return
  try {
    await api(`/alert-channels/${channel.id}`, { method: 'DELETE' })
    toast.success('Alert channel removed.')
    await refreshChannels()
  } catch (err: any) {
    toast.error(apiErrorMessage(err, 'Could not remove the channel.'))
  }
}

const formatTime = (iso: string) => {
  const d = new Date(iso)
  return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
}
</script>

<template>
  <div class="max-w-2xl">
    <h1 class="text-xl font-semibold mb-1">Alerts &amp; settings</h1>
    <p class="text-[13px] text-mt-faint mb-8">Your plan, and where mcptrax reaches you when a check fails.</p>

    <!-- Billing -->
    <section id="billing" class="mb-10 scroll-mt-20">
      <p class="mt-eyebrow mb-3">Plan &amp; billing</p>

      <div class="mt-card p-5" :class="{ 'border-mt-up/30': !isFree }">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <p class="text-[16px] font-semibold">
            {{ user?.plan_label || (isFree ? 'Free' : 'Pro') }}
            <span v-if="!isFree" class="ml-2 font-mono text-[10px] uppercase tracking-wider text-mt-up">active</span>
          </p>
          <p v-if="!isFree && proUntilLabel" class="font-mono text-[12px] text-mt-faint">renews {{ proUntilLabel }}</p>
        </div>

        <ul v-if="limitItems.length" class="mt-3 space-y-1.5 text-[13px] text-mt-muted">
          <li v-for="item in limitItems" :key="item" class="flex gap-2">
            <span class="text-mt-up font-mono">·</span> {{ item }}
          </li>
        </ul>

        <div class="mt-5 flex flex-wrap items-center gap-3">
          <UpgradeButtons v-if="isFree" />
          <button
            v-if="user?.has_billing_portal"
            type="button" class="mt-btn-ghost" :disabled="portalLoading"
            @click="openPortal"
          >
            {{ portalLoading ? 'Opening…' : 'Manage billing' }}
          </button>
        </div>

        <p v-if="portalError" class="mt-3 rounded-md border border-mt-down/25 bg-[var(--mt-down-dim)] px-3 py-2.5 text-[13px] text-mt-text">
          {{ portalError }}
        </p>
        <p v-if="portalUrl" class="mt-3 text-[13px] text-mt-muted">
          Your browser blocked the new tab —
          <a :href="portalUrl" target="_blank" rel="noopener" class="text-mt-up hover:underline font-medium">open the billing portal</a>.
        </p>
      </div>
    </section>

    <!-- Channels -->
    <section class="mb-10">
      <p class="mt-eyebrow mb-3">Alert channels</p>

      <ul v-if="channels.length" class="space-y-2 mb-4">
        <li
          v-for="channel in channels" :key="channel.id"
          class="mt-card flex items-center gap-3 px-4 py-3"
        >
          <span class="mt-chip shrink-0 w-[74px] justify-center">{{ channel.type }}</span>
          <span class="font-mono text-[13px] text-mt-text truncate flex-1">{{ channel.target }}</span>
          <span v-if="!channel.is_active" class="font-mono text-[11px] text-mt-warn shrink-0">inactive</span>
          <button class="mt-btn-danger !px-2.5 !py-1.5 shrink-0" @click="removeChannel(channel)">Remove</button>
        </li>
      </ul>
      <p v-else class="mb-4 text-[13.5px] text-mt-muted">
        No channels yet — alerts have nowhere to go. Add one below.
      </p>

      <form class="mt-card p-4 sm:p-5" @submit.prevent="addChannel">
        <div class="flex flex-col sm:flex-row gap-3">
          <div class="sm:w-[140px] shrink-0">
            <label class="mt-label" for="ch-type">Type</label>
            <select id="ch-type" v-model="newType" class="mt-input">
              <option v-for="(meta, type) in TYPE_META" :key="type" :value="type">{{ meta.label }}</option>
            </select>
          </div>
          <div class="flex-1 min-w-0">
            <label class="mt-label" for="ch-target">Target</label>
            <input
              id="ch-target" v-model="newTarget" required
              :type="newType === 'email' ? 'email' : 'url'"
              class="mt-input font-mono text-[13px]"
              :placeholder="TYPE_META[newType].placeholder"
            >
            <p v-if="targetError" class="mt-1.5 text-[12px] text-mt-down">{{ targetError }}</p>
            <p v-else class="mt-hint">{{ TYPE_META[newType].hint }}</p>
          </div>
          <div class="sm:self-end sm:pb-[22px]">
            <button type="submit" class="mt-btn-primary w-full sm:w-auto" :disabled="adding">
              {{ adding ? 'Adding…' : 'Add channel' }}
            </button>
          </div>
        </div>
      </form>

      <UpgradePrompt v-if="channelUpgradeMessage" :message="channelUpgradeMessage" class="mt-4" />
    </section>

    <!-- Alert history -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <p class="mt-eyebrow !mb-0">Alert history</p>
        <button class="font-mono text-[11.5px] text-mt-faint hover:text-mt-muted transition" @click="refreshAlerts()">refresh</button>
      </div>

      <div v-if="!alerts.length" class="mt-card px-6 py-10 text-center">
        <p class="text-[13.5px] text-mt-muted">No alerts so far. That's the goal.</p>
      </div>

      <ul v-else class="space-y-2">
        <li v-for="alert in alerts" :key="alert.id" class="mt-card px-4 py-3">
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <span
              class="inline-flex items-center gap-1.5 font-mono text-[11.5px] font-medium"
              :class="alert.resolved_at ? 'text-mt-up' : 'text-mt-down'"
            >
              <span class="w-1.5 h-1.5 rounded-full" :class="alert.resolved_at ? 'bg-mt-up' : 'bg-mt-down'" />
              {{ alert.resolved_at ? 'resolved' : 'open' }}
            </span>
            <NuxtLink :to="`/app/monitors/${alert.monitor_id}`" class="text-[13.5px] font-medium text-mt-text hover:text-mt-up transition">
              {{ alert.monitor_name }}
            </NuxtLink>
            <span class="mt-chip">{{ alert.kind }}</span>
            <span class="font-mono text-[11.5px] text-mt-faint ml-auto">
              {{ formatTime(alert.opened_at) }}{{ alert.resolved_at ? ` → ${formatTime(alert.resolved_at)}` : '' }}
            </span>
          </div>
          <p v-if="alert.reason || alert.error_message" class="mt-1.5 text-[12.5px] text-mt-muted break-words">
            {{ alert.reason || alert.error_message }}
          </p>
        </li>
      </ul>
    </section>
  </div>
</template>
